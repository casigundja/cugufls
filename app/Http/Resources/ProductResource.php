<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->productImages->sortBy('sort_order')->values()->map(fn ($i) => ['url' => Storage::disk('public')->url($i->path), 'alt' => $i->alt]);

        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'short_description' => $this->short_description,
            'description' => $this->description, 'price' => $this->price, 'sale_price' => $this->sale_price, 'currency' => 'AOA',
            'unit' => $this->unit, 'purchase_mode' => $this->purchase_mode, 'availability' => 'on_request',
            'segment' => $this->segment->only('id', 'name', 'slug'), 'category' => $this->category->only('id', 'name', 'slug'),
            'brand' => $this->brand?->only('id', 'name', 'slug'), 'images' => $images, 'primary_image_url' => $images->first()['url'] ?? null];
    }
}
