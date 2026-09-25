<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Post;
use App\Models\Segment;
use App\Models\Service;
use App\Services\PublicCatalog;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function products(Request $request, PublicCatalog $catalog)
    {
        return ProductResource::collection($catalog->products($request)->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function product(Request $request, PublicCatalog $catalog, string $slug)
    {
        return new ProductResource($catalog->products($request)->where('slug', $slug)->firstOrFail());
    }

    public function index(Request $request, string $resource, ?string $slug = null)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100']]);
        [$model, $fields] = match ($resource) {
            'segments' => [Segment::class, ['id', 'name', 'slug', 'description', 'image']],
            'branches' => [Branch::class, ['id', 'name', 'slug', 'city', 'province', 'address', 'phone', 'whatsapp', 'email', 'opening_hours', 'latitude', 'longitude']],
            'categories' => [Category::class, ['id', 'segment_id', 'parent_id', 'name', 'slug', 'description']],
            'services' => [Service::class, ['id', 'segment_id', 'name', 'slug', 'description', 'price', 'image']],
            'posts' => [Post::class, ['id', 'segment_id', 'title', 'slug', 'excerpt', 'content', 'image', 'published_at']],
            default => abort(404),
        };
        $query = $model::query()->select($fields);
        if ($resource === 'posts') {
            $query->where('status', 'published')->where('published_at', '<=', now());
        } else {
            $query->where('active', true);
        }
        if (in_array($resource, ['categories', 'services'])) {
            $query->whereHas('segment', fn ($q) => $q->where('active', true));
        }
        if ($resource === 'posts') {
            $query->where(fn ($q) => $q->whereNull('segment_id')->orWhereHas('segment', fn ($s) => $s->where('active', true)));
        }
        if ($request->filled('segment')) {
            if ($resource === 'branches') {
                $query->whereHas('segments', fn ($q) => $q->where('slug', $request->string('segment')));
            } elseif (in_array($resource, ['categories', 'services', 'posts'])) {
                $query->whereHas('segment', fn ($q) => $q->where('slug', $request->string('segment'))->where('active', true));
            }
        }

        return $slug ? response()->json(['data' => $query->where('slug', $slug)->firstOrFail()]) : $query->paginate($request->integer('per_page', 20));
    }
}
