<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public const TRANSITIONS = ['new' => ['contacted', 'confirmed', 'cancelled'], 'contacted' => ['confirmed', 'cancelled'], 'confirmed' => ['preparing', 'cancelled'], 'preparing' => ['ready', 'cancelled'], 'ready' => ['delivered', 'cancelled'], 'delivered' => [], 'cancelled' => []];

    public function create(array $data, string $key, string $source = 'website'): Order
    {
        $hash = hash_hmac('sha256', json_encode(['source' => $source, 'data' => $data], JSON_THROW_ON_ERROR), config('app.key'));
        $existing = Order::where('idempotency_key', $key)->first();
        if ($existing) {
            abort_unless(hash_equals($existing->request_hash, $hash), 409);

            return $existing;
        }
        try {
            return DB::transaction(function () use ($data, $key, $hash, $source) {
                $branch = Branch::where('active', true)->whereHas('segments', fn ($q) => $q->whereKey($data['segment_id'])->where('active', true))->findOrFail($data['branch_id']);
                $customer = new Customer;
                $customer->forceFill(collect($data['customer'])->only(['name', 'phone', 'email', 'company'])->all())->save();
                $order = new Order;
                $order->forceFill(['public_id' => (string) Str::ulid(), 'segment_id' => $data['segment_id'], 'branch_id' => $branch->id,
                    'customer_id' => $customer->id, 'status' => 'new', 'source' => $source, 'currency' => 'AOA',
                    'customer_snapshot' => $customer->only('name', 'phone', 'email', 'company'), 'notes' => $data['notes'] ?? null,
                    'idempotency_key' => $key, 'request_hash' => $hash])->save();
                $total = '0.00';
                foreach ($data['items'] as $index => $item) {
                    $isProduct = ! empty($item['product_id']);
                    if ($isProduct === ! empty($item['service_id'])) {
                        throw ValidationException::withMessages(["items.$index" => 'Escolha um produto ou um serviço.']);
                    }
                    $entity = ($isProduct ? Product::query()->whereHas('category', fn ($q) => $q->where('active', true)) : Service::query())->where('active', true)->where('segment_id', $data['segment_id'])->findOrFail($item[$isProduct ? 'product_id' : 'service_id']);
                    if ($isProduct && $entity->purchase_mode === 'catalog_only') {
                        throw ValidationException::withMessages(["items.$index" => 'Produto disponível apenas para consulta do catálogo.']);
                    }
                    $unit = $isProduct ? $entity->unit : 'unit';
                    if ($unit === 'unit' && bccomp($item['quantity'], bcadd($item['quantity'], '0', 0), 3) !== 0) {
                        throw ValidationException::withMessages(["items.$index.quantity" => 'Indique uma quantidade inteira.']);
                    }
                    $price = $isProduct ? ($entity->sale_price ?? $entity->price) : $entity->price;
                    $lineTotal = $price === null ? null : bcmul($price, $item['quantity'], 2);
                    $total = $total === null || $lineTotal === null ? null : bcadd($total, $lineTotal, 2);
                    (new OrderItem)->forceFill(['order_id' => $order->id, 'product_id' => $isProduct ? $entity->id : null,
                        'service_id' => $isProduct ? null : $entity->id, 'name' => $entity->name, 'sku' => $isProduct ? $entity->sku : null,
                        'unit' => $unit, 'quantity' => $item['quantity'], 'unit_price' => $price, 'line_total' => $lineTotal,
                        'customization' => $item['customization'] ?? null])->save();
                }
                $order->total = $total;
                $order->save();
                (new OrderStatusHistory)->forceFill(['order_id' => $order->id, 'user_id' => auth()->id(), 'to_status' => 'new'])->save();
                Audit::record('order.created', $order);

                return $order;
            });
        } catch (QueryException $exception) {
            $existing = Order::where('idempotency_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }
            abort_unless(hash_equals($existing->request_hash, $hash), 409);

            return $existing;
        }
    }

    public function transition(User $user, Order $order, string $status, ?string $reason): void
    {
        app(Access::class)->authorize($user, $status === 'cancelled' ? 'orders.cancel' : 'orders.update', $order->segment_id, $order->branch_id);
        if ($status === 'delivered') {
            app(Access::class)->authorize($user, 'orders.deliver', $order->segment_id, $order->branch_id);
        }
        DB::transaction(function () use ($user, $order, $status, $reason) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->status === $status) {
                return;
            }
            abort_unless(in_array($status, self::TRANSITIONS[$order->status]), 409, 'Transição de estado inválida.');
            if ($status === 'cancelled' && ! $reason) {
                throw ValidationException::withMessages(['reason' => 'Indique o motivo.']);
            }
            (new OrderStatusHistory)->forceFill(['order_id' => $order->id, 'user_id' => $user->id, 'from_status' => $order->status, 'to_status' => $status, 'reason' => $reason])->save();
            $order->status = $status;
            $order->save();
            Audit::record('order.'.$status, $order);
        });
    }

    public function receipt(Order $order): array
    {
        $order->load('branch', 'orderItems');
        $number = preg_replace('/\D/', '', $order->branch->whatsapp ?? '');
        $text = "Olá, Família Gundja!\nPedido ".$order->public_id."\n";
        foreach ($order->orderItems as $item) {
            $text .= $item->name.' — '.$item->quantity."\n";
        }
        $text .= 'Local: '.$order->branch->name."\nGostaria de confirmar a disponibilidade. Obrigado.";

        return ['reference' => $order->public_id, 'status' => $order->status, 'currency' => 'AOA', 'quoted_total' => $order->total,
            'whatsapp_url' => $number ? 'https://wa.me/'.$number.'?text='.rawurlencode($text) : null];
    }
}
