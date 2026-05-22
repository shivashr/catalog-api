<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'brands' => $this->whenLoaded('brands', fn () => $this->brands
                ->map(fn ($brand): array => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'status' => $brand->status,
                ])
                ->values()),
            'sub_categories' => $this->whenLoaded('subCategories', fn () => $this->subCategories
                ->map(fn ($subCategory): array => [
                    'id' => $subCategory->id,
                    'category_id' => $subCategory->category_id,
                    'name' => $subCategory->name,
                    'slug' => $subCategory->slug,
                    'status' => $subCategory->status,
                ])
                ->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
