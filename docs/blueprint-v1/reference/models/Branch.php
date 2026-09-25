<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class Branch extends Model
{
    protected $table = 'branches';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'opening_hours' => 'array',
            'active' => 'boolean',
        ];
    }

    public function userAccesses(): HasMany
    {
        return $this->hasMany(UserAccess::class, 'branch_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'branch_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'branch_id');
    }

    public function sourceBranchStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_branch_id');
    }

    public function destinationBranchStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_branch_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'branch_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'branch_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'branch_id');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'branch_id');
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'branch_segment')->withTimestamps();
    }
}
