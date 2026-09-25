<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Notifications\StockAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private Access $access) {}

    public function adjust(User $user, array $data): StockMovement
    {
        $product = Product::findOrFail($data['product_id']);
        $this->access->authorize($user, 'stock.update', $product->segment_id, (int) $data['branch_id']);

        return DB::transaction(function () use ($user, $product, $data) {
            $type = $data['type'] ?? 'adjustment';
            $inventory = $this->lockedInventory($product, (int) $data['branch_id']);
            $previous = StockMovement::where('operation_id', $data['operation_id'])->first();
            if ($previous) {
                $target = $type === 'adjustment' ? $data['quantity'] : ($type === 'exit' ? bcsub($data['expected_quantity'], $data['quantity'], 3) : bcadd($data['expected_quantity'], $data['quantity'], 3));
                abort_unless($previous->product_id === $product->id && $previous->branch_id === (int) $data['branch_id'] && $previous->type === $type && bccomp($previous->new_quantity, $target, 3) === 0, 409);

                return $previous;
            }
            if (bccomp($inventory->quantity, $data['expected_quantity'], 3) !== 0) {
                throw ValidationException::withMessages(['quantity' => 'O saldo mudou. Atualize a página antes de ajustar.']);
            }
            $delta = $type === 'adjustment' ? bcsub($data['quantity'], $inventory->quantity, 3) : ($type === 'exit' ? bcsub('0', $data['quantity'], 3) : (string) $data['quantity']);
            $movement = $this->move($user, $product, $inventory, $delta, $type, $data['operation_id'], $data['reason']);
            if ($type === 'adjustment') {
                $inventory->last_counted_at = now();
            }
            $inventory->save();

            return $movement;
        }, 3);
    }

    private function lockedInventory(Product $product, int $branch): Inventory
    {
        $location = Branch::where('active', true)->findOrFail($branch);
        abort_unless($location->segments()->whereKey($product->segment_id)->exists(), 422, 'Filial não associada ao segmento.');
        Inventory::query()->insertOrIgnore(['product_id' => $product->id, 'branch_id' => $branch, 'quantity' => 0, 'minimum_quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return Inventory::where('product_id', $product->id)->where('branch_id', $branch)->lockForUpdate()->firstOrFail();
    }

    private function move(User $user, Product $product, Inventory $inventory, string $delta, string $type, string $operation, string $reason, ?int $transferItem = null): StockMovement
    {
        if ($product->unit === 'unit' && bccomp($delta, bcadd($delta, '0', 0), 3) !== 0) {
            throw ValidationException::withMessages(['quantity' => 'Este produto exige quantidade inteira.']);
        }
        $next = bcadd($inventory->quantity, $delta, 3);
        if (bccomp($next, '0', 3) < 0) {
            throw ValidationException::withMessages(['quantity' => 'Stock insuficiente.']);
        }
        $movement = new StockMovement;
        $movement->forceFill(['product_id' => $product->id, 'branch_id' => $inventory->branch_id, 'user_id' => $user->id,
            'type' => $type, 'quantity' => $delta, 'previous_quantity' => $inventory->quantity, 'new_quantity' => $next,
            'reason' => $reason, 'operation_id' => $operation, 'transfer_item_id' => $transferItem])->save();
        $crossed = bccomp($inventory->quantity, $inventory->minimum_quantity, 3) > 0 && bccomp($next, $inventory->minimum_quantity, 3) <= 0;
        $inventory->quantity = $next;
        $inventory->save();
        Audit::record('stock.'.$type, $movement, [], $movement->toArray());
        if ($crossed) {
            foreach (User::where('active', true)->cursor() as $recipient) {
                if ($this->access->allows($recipient, 'stock.view', $product->segment_id, $inventory->branch_id)) {
                    $recipient->notify(new StockAlert($product->name, $inventory->branch->name, $next, $inventory->branch_id));
                }
            }
        }

        return $movement;
    }

    public function createTransfer(User $user, array $data): StockTransfer
    {
        $this->access->authorize($user, 'stock.transfer', (int) $data['segment_id'], (int) $data['source_branch_id']);

        return DB::transaction(function () use ($user, $data) {
            foreach (['source_branch_id', 'destination_branch_id'] as $key) {
                abort_unless(Branch::where('active', true)->whereKey($data[$key])->whereHas('segments', fn ($q) => $q->whereKey($data['segment_id']))->exists(), 422, 'Filial inválida.');
            }
            $transfer = new StockTransfer;
            $transfer->forceFill(['reference' => (string) Str::ulid(), 'segment_id' => $data['segment_id'], 'source_branch_id' => $data['source_branch_id'],
                'destination_branch_id' => $data['destination_branch_id'], 'created_by' => $user->id, 'status' => 'draft', 'notes' => $data['notes'] ?? null])->save();
            foreach ($data['items'] as $item) {
                $product = Product::where('segment_id', $data['segment_id'])->where('active', true)->findOrFail($item['product_id']);
                if ($product->unit === 'unit' && bccomp($item['quantity'], bcadd($item['quantity'], '0', 0), 3) !== 0) {
                    throw ValidationException::withMessages(['items' => 'Produto por unidade exige quantidade inteira.']);
                }
                (new StockTransferItem)->forceFill(['stock_transfer_id' => $transfer->id, 'product_id' => $product->id, 'quantity' => $item['quantity']])->save();
            }
            Audit::record('transfer.created', $transfer);

            return $transfer;
        });
    }

    public function transfer(User $user, StockTransfer $transfer, string $action): StockTransfer
    {
        return DB::transaction(function () use ($user, $transfer, $action) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $receiving = $action === 'receive';
            $branch = $receiving ? $transfer->destination_branch_id : $transfer->source_branch_id;
            $permission = $receiving ? 'transfers.receive' : ($action === 'dispatch' ? 'transfers.dispatch' : 'stock.transfer');
            $this->access->authorize($user, $permission, $transfer->segment_id, $branch);
            $target = ['receive' => 'received', 'dispatch' => 'dispatched', 'cancel' => 'cancelled'][$action];
            if ($transfer->status === $target) {
                return $transfer;
            }
            abort_unless($transfer->status === ($receiving ? 'dispatched' : 'draft'), 409, 'Transição inválida.');
            if ($action !== 'cancel') {
                foreach ($transfer->stockTransferItems()->with('product')->orderBy('product_id')->get() as $item) {
                    $inventory = $this->lockedInventory($item->product, $branch);
                    $this->move($user, $item->product, $inventory, $receiving ? $item->quantity : bcsub('0', $item->quantity, 3),
                        $receiving ? 'transfer_in' : 'transfer_out', hash('sha256', $transfer->id.':'.$item->id.':'.$action),
                        'Transferência '.$transfer->reference, $item->id);
                }
            }
            $transfer->status = $target;
            if ($receiving) {
                $transfer->received_at = now();
                $transfer->received_by = $user->id;
            }
            if ($action === 'dispatch') {
                $transfer->dispatched_at = now();
            }
            $transfer->save();
            Audit::record('transfer.'.$action, $transfer);

            return $transfer;
        }, 3);
    }

    public function updateDraft(User $user, StockTransfer $transfer, array $data): void
    {
        $this->access->authorize($user, 'stock.transfer', $transfer->segment_id, $transfer->source_branch_id);
        DB::transaction(function () use ($transfer, $data) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            abort_unless($transfer->status === 'draft', 409, 'Só é possível editar um rascunho.');
            $transfer->stockTransferItems()->delete();
            foreach ($data['items'] as $item) {
                $product = Product::where('segment_id', $transfer->segment_id)->where('active', true)->findOrFail($item['product_id']);
                if ($product->unit === 'unit' && bccomp($item['quantity'], bcadd($item['quantity'], '0', 0), 3) !== 0) {
                    throw ValidationException::withMessages(['items' => 'Produto por unidade exige quantidade inteira.']);
                }
                (new StockTransferItem)->forceFill(['stock_transfer_id' => $transfer->id, 'product_id' => $product->id, 'quantity' => $item['quantity']])->save();
            }
            $transfer->notes = $data['notes'] ?? null;
            $transfer->save();
            Audit::record('transfer.updated', $transfer);
        });
    }
}
