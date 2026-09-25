<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class Customer extends Model
{
    protected $table = 'customers';

    protected $guarded = ['*'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
