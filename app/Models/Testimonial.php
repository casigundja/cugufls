<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class Testimonial extends Model
{
    protected $table = 'testimonials';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'consented_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }
}
