<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $guarded = ['*'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'previous_quantity' => 'decimal:3',
            'new_quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'transfer_item_id');
    }
}
