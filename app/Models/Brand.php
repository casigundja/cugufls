<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class Brand extends Model
{
    protected $table = 'brands';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
