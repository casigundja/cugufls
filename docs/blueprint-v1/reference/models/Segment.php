<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Referência estrutural; validar e autorizar nas Actions antes de persistir.
class Segment extends Model
{
    protected $table = 'segments';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function userAccesses(): HasMany
    {
        return $this->hasMany(UserAccess::class, 'segment_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'segment_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'segment_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'segment_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'segment_id');
    }

    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'segment_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'segment_id');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class, 'segment_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'segment_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'segment_id');
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class, 'segment_id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class, 'segment_id');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'segment_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_segment')->withTimestamps();
    }
}
