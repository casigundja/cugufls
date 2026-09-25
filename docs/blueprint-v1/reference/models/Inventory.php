<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class Inventory extends Model
{
    protected $table = 'inventories';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'minimum_quantity' => 'decimal:3',
            'maximum_quantity' => 'decimal:3',
            'last_counted_at' => 'datetime',
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
}
