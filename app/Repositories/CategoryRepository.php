<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->with(['brands', 'subCategories'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate($perPage);
    }

    public function activeForTenant(int $tenantId): Collection
    {
        return Category::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function findForTenant(int $tenantId, int $categoryId): Category
    {
        return Category::query()
            ->with(['brands', 'subCategories'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($categoryId);
    }

    public function slugExistsForTenant(int $tenantId, string $slug, ?int $ignoreCategoryId = null): bool
    {
        return Category::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreCategoryId, fn ($query) => $query->whereKeyNot($ignoreCategoryId))
            ->exists();
    }

    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function syncBrands(Category $category, array $brandIds, int $tenantId): void
    {
        $syncPayload = collect($brandIds)
            ->mapWithKeys(fn (int $brandId): array => [$brandId => ['tenant_id' => $tenantId]])
            ->all();

        $category->brands()->sync($syncPayload);
    }

    public function isUsedByProducts(Category $category): bool
    {
        return $category->products()->exists();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
