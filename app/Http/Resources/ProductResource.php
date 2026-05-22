<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'sub_category_id' => $this->sub_category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'model_number' => $this->model_number,
            'condition' => $this->condition,
            'description' => $this->description,
            'tags' => $this->tags ?? [],
            'selling_price' => $this->selling_price,
            'mrp_price' => $this->mrp_price,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_alert' => $this->low_stock_alert,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'cost_price' => $this->cost_price,
            'tax_rate' => $this->tax_rate,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'shipping_class' => $this->shipping_class,
            'free_shipping' => $this->free_shipping,
            'status' => $this->status,
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'status' => $this->category->status,
            ] : null),
            'brand' => $this->whenLoaded('brandModel', fn (): ?array => $this->brandModel ? [
                'id' => $this->brandModel->id,
                'name' => $this->brandModel->name,
                'slug' => $this->brandModel->slug,
                'status' => $this->brandModel->status,
            ] : null),
            'sub_category' => $this->whenLoaded('subCategoryModel', fn (): ?array => $this->subCategoryModel ? [
                'id' => $this->subCategoryModel->id,
                'category_id' => $this->subCategoryModel->category_id,
                'name' => $this->subCategoryModel->name,
                'slug' => $this->subCategoryModel->slug,
                'status' => $this->subCategoryModel->status,
            ] : null),
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($image): array => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'url' => Storage::disk('public')->url($image->image_path),
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                ])),
            'variants' => $this->whenLoaded('variants', fn () => $this->imagesGroupedByVariantType()),
            'specifications' => $this->whenLoaded('specifications', fn () => $this->specifications
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($specification): array => [
                    'id' => $specification->id,
                    'attribute' => $specification->attribute,
                    'value' => $specification->value,
                    'sort_order' => $specification->sort_order,
                ])),
            'warranty' => $this->whenLoaded('warranty', fn () => $this->warranty ? [
                'warranty_duration' => $this->warranty->warranty_duration,
                'warranty_unit' => $this->warranty->warranty_unit,
                'warranty_by' => $this->warranty->warranty_by,
                'return_window' => $this->warranty->return_window,
                'return_type' => $this->warranty->return_type,
            ] : null),
            'seo' => [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function imagesGroupedByVariantType(): array
    {
        return $this->variants
            ->groupBy('type')
            ->map(fn ($variants) => $variants->pluck('value')->values())
            ->union([
                'size' => [],
                'color' => [],
                'material' => [],
            ])
            ->all();
    }
}
