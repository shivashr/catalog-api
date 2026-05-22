<?php

namespace App\Repositories;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrandRepository
{
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return Brand::query()
            ->with('categories')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate($perPage);
    }

    public function activeForCategory(int $tenantId, int $categoryId): Collection
    {
        return Brand::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('categories', fn ($query) => $query->where('categories.id', $categoryId))
            ->orderBy('name')
            ->get();
    }

    public function findForTenant(int $tenantId, int $brandId): Brand
    {
        return Brand::query()
            ->with('categories')
            ->where('tenant_id', $tenantId)
            ->findOrFail($brandId);
    }

    public function slugExistsForTenant(int $tenantId, string $slug, ?int $ignoreBrandId = null): bool
    {
        return Brand::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreBrandId, fn ($query) => $query->whereKeyNot($ignoreBrandId))
            ->exists();
    }

    public function create(array $data): Brand
    {
        return Brand::query()->create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand;
    }

    public function isUsedByProducts(Brand $brand): bool
    {
        return $brand->products()->exists();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}
