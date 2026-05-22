<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->with(['images', 'variants', 'specifications', 'warranty'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate($perPage);
    }

    public function findForTenant(int $tenantId, int $productId): Product
    {
        return Product::query()
            ->with(['images', 'variants', 'specifications', 'warranty'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($productId);
    }

    public function slugExistsForTenant(int $tenantId, string $slug, ?int $ignoreProductId = null): bool
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreProductId, fn ($query) => $query->whereKeyNot($ignoreProductId))
            ->exists();
    }

    public function skuExistsForTenant(int $tenantId, string $sku, ?int $ignoreProductId = null): bool
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->when($ignoreProductId, fn ($query) => $query->whereKeyNot($ignoreProductId))
            ->exists();
    }

    public function create(array $data): Product
    {
        return Product::query()->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function replaceVariants(Product $product, array $variants): void
    {
        $product->variants()->delete();
        $product->variants()->createMany($variants);
    }

    public function replaceSpecifications(Product $product, array $specifications): void
    {
        $product->specifications()->delete();
        $product->specifications()->createMany($specifications);
    }

    public function upsertWarranty(Product $product, ?array $warranty): void
    {
        if ($warranty === null) {
            return;
        }

        $product->warranty()->updateOrCreate([], $warranty);
    }

    public function addImages(Product $product, array $images): Collection
    {
        return $product->images()->createMany($images);
    }

    public function findImageForProduct(Product $product, int $imageId): ProductImage
    {
        return $product->images()->findOrFail($imageId);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
