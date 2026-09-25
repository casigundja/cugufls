<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class ImportRow extends Model
{
    protected $table = 'import_rows';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
