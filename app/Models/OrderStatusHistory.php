<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class OrderStatusHistory extends Model
{
    protected $table = 'order_status_histories';

    protected $guarded = ['*'];

    public const UPDATED_AT = null;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
