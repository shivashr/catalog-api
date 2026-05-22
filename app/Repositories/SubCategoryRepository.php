<?php

namespace App\Repositories;

use App\Models\SubCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SubCategoryRepository
{
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return SubCategory::query()
            ->with('category')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate($perPage);
    }

    public function activeForCategory(int $tenantId, int $categoryId): Collection
    {
        return SubCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function findForTenant(int $tenantId, int $subCategoryId): SubCategory
    {
        return SubCategory::query()
            ->with('category')
            ->where('tenant_id', $tenantId)
            ->findOrFail($subCategoryId);
    }

    public function slugExistsForTenant(int $tenantId, string $slug, ?int $ignoreSubCategoryId = null): bool
    {
        return SubCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreSubCategoryId, fn ($query) => $query->whereKeyNot($ignoreSubCategoryId))
            ->exists();
    }

    public function create(array $data): SubCategory
    {
        return SubCategory::query()->create($data);
    }

    public function update(SubCategory $subCategory, array $data): SubCategory
    {
        $subCategory->update($data);

        return $subCategory;
    }

    public function isUsedByProducts(SubCategory $subCategory): bool
    {
        return $subCategory->products()->exists();
    }

    public function delete(SubCategory $subCategory): void
    {
        $subCategory->delete();
    }
}
