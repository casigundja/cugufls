<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class Post extends Model
{
    protected $table = 'posts';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
