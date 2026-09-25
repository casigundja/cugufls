<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicCatalog
{
    public function products(Request $request, ?int $segmentId = null): Builder
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string'], 'brand' => ['nullable', 'string'],
            'branch' => ['nullable', 'string'], 'sort' => ['nullable', Rule::in(['name', '-name', 'price', '-price', 'newest'])],
            'availability' => ['nullable', Rule::in(['available', 'unavailable', 'on_request'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = Product::where('active', true)->whereHas('segment', fn ($q) => $q->where('active', true))->whereHas('category', fn ($q) => $q->where('active', true))->with(['segment', 'category', 'brand', 'productImages']);
        if ($segmentId) {
            $query->where('segment_id', $segmentId);
        } elseif ($request->filled('segment')) {
            $query->whereHas('segment', fn ($q) => $q->where('slug', $request->string('segment')));
        }
        if (! empty($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }
        if (! empty($data['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $data['category']));
        }
        if (! empty($data['brand'])) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $data['brand']));
        }
        if (! empty($data['branch'])) {
            $query->whereHas('inventories.branch', fn ($q) => $q->where('slug', $data['branch'])->where('active', true));
            if (in_array($data['availability'] ?? null, ['available', 'unavailable'])) {
                $query->whereHas('inventories', fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('slug', $data['branch']))->where('quantity', $data['availability'] === 'available' ? '>' : '=', 0));
            }
        }
        if (($data['availability'] ?? null) === 'on_request') {
            $query->where('purchase_mode', 'request_availability');
        }

        return match ($data['sort'] ?? 'newest') {
            'name' => $query->orderBy('name'), '-name' => $query->orderByDesc('name'),
            'price' => $query->orderByRaw('price IS NULL')->orderBy('price'), '-price' => $query->orderByRaw('price IS NULL')->orderByDesc('price'),
            default => $query->latest(),
        };
    }
}
