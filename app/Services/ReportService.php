<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Segment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportService
{
    public const TYPES = ['stock', 'movements', 'orders', 'products', 'segments', 'branches'];

    public function filters(Request $request): array
    {
        return $request->validate(['type' => ['required', 'in:'.implode(',', self::TYPES)], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'segment_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer']]);
    }

    public function query(User $user, array $filters): Builder
    {
        $access = app(Access::class);
        $type = $filters['type'];
        abort_unless($access->any($user, 'reports.'.$type), 403);
        $model = ['stock' => Inventory::class, 'movements' => StockMovement::class, 'orders' => Order::class, 'products' => Product::class, 'segments' => Segment::class, 'branches' => Branch::class][$type];
        $query = $access->scope($user, $model::query(), 'reports.'.$type);
        if (in_array($type, ['stock', 'movements'])) {
            $query->with('product:id,name,segment_id', 'branch:id,name');
        }
        if (! empty($filters['segment_id'])) {
            $segment = $filters['segment_id'];
            if (in_array($type, ['stock', 'movements'])) {
                $query->whereHas('product', fn ($q) => $q->where('segment_id', $segment));
            } elseif (in_array($type, ['orders', 'products'])) {
                $query->where('segment_id', $segment);
            } elseif ($type === 'segments') {
                $query->whereKey($segment);
            } else {
                $query->whereHas('segments', fn ($q) => $q->whereKey($segment));
            }
        }
        if (! empty($filters['branch_id'])) {
            $branch = $filters['branch_id'];
            if (in_array($type, ['stock', 'movements', 'orders'])) {
                $query->where('branch_id', $branch);
            } elseif ($type === 'products') {
                $query->whereHas('inventories', fn ($q) => $q->where('branch_id', $branch));
            } elseif ($type === 'branches') {
                $query->whereKey($branch);
            } else {
                $query->whereHas('branches', fn ($q) => $q->where('branches.id', $branch));
            }
        }
        if (in_array($type, ['movements', 'orders'])) {
            $this->period($query, $filters);
        }

        return $query->orderByDesc('id');
    }

    private function period(Builder $query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'].' 23:59:59');
        }
    }

    public function row(User $user, object $row, array $filters): array
    {
        return match ($filters['type']) {
            'stock' => ['Produto' => $row->product->name, 'Filial' => $row->branch->name, 'Stock' => $row->quantity, 'Mínimo' => $row->minimum_quantity, 'Última contagem' => (string) $row->last_counted_at],
            'movements' => ['Produto' => $row->product->name, 'Filial' => $row->branch->name, 'Tipo' => $row->type, 'Quantidade' => $row->quantity, 'Anterior' => $row->previous_quantity, 'Novo' => $row->new_quantity, 'Data' => (string) $row->created_at],
            'orders' => ['Referência' => $row->public_id, 'Estado' => $row->status, 'Total cotado' => $row->total, 'Data' => (string) $row->created_at],
            'products' => ['Produto' => $row->name, 'SKU' => $row->sku, 'Preço' => $row->price, 'Ativo' => $row->active ? 'Sim' : 'Não'],
            default => $this->summary($user, $row, $filters),
        };
    }

    private function summary(User $user, object $row, array $filters): array
    {
        $access = app(Access::class);
        $orders = $access->scope($user, Order::query(), 'reports.'.$filters['type']);
        $stock = $access->scope($user, Inventory::query(), 'reports.'.$filters['type']);
        if ($filters['type'] === 'segments') {
            $orders->where('segment_id', $row->id);
            $stock->whereHas('product', fn ($q) => $q->where('segment_id', $row->id));
            if (! empty($filters['branch_id'])) {
                $orders->where('branch_id', $filters['branch_id']);
                $stock->where('branch_id', $filters['branch_id']);
            }
        } else {
            $orders->where('branch_id', $row->id);
            $stock->where('branch_id', $row->id);
            if (! empty($filters['segment_id'])) {
                $orders->where('segment_id', $filters['segment_id']);
                $stock->whereHas('product', fn ($q) => $q->where('segment_id', $filters['segment_id']));
            }
        }
        $this->period($orders, $filters);

        return ['Nome' => $row->name, 'Pedidos no período' => $orders->count(), 'Produtos com inventário' => (clone $stock)->distinct()->count('product_id'), 'Saldos no mínimo' => $stock->whereColumn('quantity', '<=', 'minimum_quantity')->count()];
    }

    public function fingerprint(User $user): string
    {
        $user->unsetRelation('roles');

        return hash('sha256', json_encode([
            'active' => $user->active,
            'roles' => $user->roles()->with('permissions:id,name')->orderBy('id')->get()->toArray(),
            'grants' => $user->userAccesses()->with('role.permissions')->orderBy('id')->get()->toArray(),
        ], JSON_THROW_ON_ERROR));
    }
}
