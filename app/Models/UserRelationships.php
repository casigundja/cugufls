<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

// Acrescentar ao User existente junto de HasRoles após instalar Spatie.
trait UserRelationships
{
    public function userAccesses(): HasMany
    {
        return $this->hasMany(UserAccess::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_to');
    }

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'user_id');
    }

    public function creatorStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'created_by');
    }

    public function receiverStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'received_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'updated_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'assigned_to');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'updated_by');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'created_by');
    }
}
