<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Access
{
    public function any(User $user, string $permission, bool $segmentWide = false): bool
    {
        return $this->global($user, $permission) || ($user->active && $user->userAccesses()
            ->when($segmentWide, fn ($q) => $q->whereNull('branch_id'))
            ->whereHas('role.permissions', fn ($q) => $q->where('name', $permission))->exists());
    }

    public function global(User $user, string $permission): bool
    {
        return $user->active && ($user->hasRole('super-admin') || ($user->hasRole('admin') && $user->checkPermissionTo($permission)));
    }

    public function allows(User $user, string $permission, ?int $segment = null, ?int $branch = null, bool $segmentWide = false): bool
    {
        if (! $user->active) {
            return false;
        }
        if ($this->global($user, $permission)) {
            return true;
        }
        if ($segment === null) {
            return false;
        }

        return $user->userAccesses()->with('role.permissions')->get()->contains(function ($access) use ($permission, $segment, $branch, $segmentWide) {
            return $access->role->permissions->contains('name', $permission)
                && ($segment === null || $access->segment_id === $segment)
                && (! $segmentWide || $access->branch_id === null)
                && ($access->branch_id === null || ($branch !== null && $access->branch_id === $branch));
        });
    }

    public function scope(User $user, Builder $query, string $permission): Builder
    {
        if ($this->global($user, $permission)) {
            return $query;
        }
        if (! $user->active) {
            return $query->whereRaw('1 = 0');
        }
        $table = $query->getModel()->getTable();
        $grants = $user->userAccesses()->with('role.permissions')->get()->filter(fn ($g) => $g->role->permissions->contains('name', $permission));

        return $query->where(function (Builder $scope) use ($grants, $table) {
            $scope->whereRaw('1 = 0');
            foreach ($grants as $grant) {
                $scope->orWhere(function (Builder $part) use ($grant, $table) {
                    $segment = $grant->segment_id;
                    $branch = $grant->branch_id;
                    if (in_array($table, ['inventories', 'stock_movements'])) {
                        $part->whereHas('product', fn ($q) => $q->where('segment_id', $segment));
                        if ($branch) {
                            $part->where('branch_id', $branch);
                        }
                    } elseif ($table === 'stock_transfers') {
                        $part->where('segment_id', $segment);
                        if ($branch) {
                            $part->where(fn ($q) => $q->where('source_branch_id', $branch)->orWhere('destination_branch_id', $branch));
                        }
                    } elseif ($table === 'branches') {
                        $part->whereHas('segments', fn ($q) => $q->where('segments.id', $segment));
                        if ($branch) {
                            $part->whereKey($branch);
                        }
                    } elseif ($table === 'segments') {
                        $part->whereKey($segment);
                    } elseif ($table === 'customers') {
                        $part->whereHas('orders', function ($q) use ($segment, $branch) {
                            $q->where('segment_id', $segment);
                            if ($branch) {
                                $q->where('branch_id', $branch);
                            }
                        });
                    } elseif ($table === 'brands' || $table === 'product_images') {
                        $part->whereHas($table === 'brands' ? 'products' : 'product', fn ($q) => $q->where('segment_id', $segment));
                    } elseif (in_array($table, ['products', 'categories', 'services', 'posts', 'banners', 'faqs', 'testimonials', 'orders', 'contacts', 'team_members', 'import_batches'])) {
                        $part->where('segment_id', $segment);
                        if ($branch && in_array($table, ['orders', 'contacts', 'team_members', 'import_batches'])) {
                            $part->where('branch_id', $branch);
                        }
                        if ($branch && $table === 'products') {
                            $part->whereHas('inventories', fn ($q) => $q->where('branch_id', $branch));
                        }
                    } else {
                        $part->whereRaw('1 = 0');
                    }
                });
            }
        });
    }

    public function authorize(User $user, string $permission, ?int $segment = null, ?int $branch = null, bool $segmentWide = false): void
    {
        abort_unless($this->allows($user, $permission, $segment, $branch, $segmentWide), 403);
    }

    public function record(User $user, Model $model, string $permission): void
    {
        abort_unless($this->scope($user, $model->newQuery(), $permission)->whereKey($model->getKey())->exists(), 404);
    }
}
