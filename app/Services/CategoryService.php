<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly BrandRepository $brands,
    ) {
    }

    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->categories->paginateForTenant($this->tenantId($user), $perPage);
    }

    public function options(User $user): Collection
    {
        return $this->categories->activeForTenant($this->tenantId($user));
    }

    public function show(User $user, int $categoryId): Category
    {
        return $this->categories->findForTenant($this->tenantId($user), $categoryId);
    }

    public function create(User $user, array $data): Category
    {
        $tenantId = $this->tenantId($user);
        $data['tenant_id'] = $tenantId;
        $data['slug'] = $this->uniqueSlug($tenantId, $data['slug'] ?? null, $data['name']);
        $data['status'] = $data['status'] ?? 'active';

        return $this->categories->create($data)->load(['brands', 'subCategories']);
    }

    public function update(User $user, int $categoryId, array $data): Category
    {
        $tenantId = $this->tenantId($user);
        $category = $this->categories->findForTenant($tenantId, $categoryId);

        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug(
                $tenantId,
                $data['slug'] ?? $category->slug,
                $data['name'] ?? $category->name,
                $category->id
            );
        }

        return $this->categories->update($category, $data)->load(['brands', 'subCategories']);
    }

    public function syncBrands(User $user, int $categoryId, array $brandIds): Category
    {
        $tenantId = $this->tenantId($user);
        $category = $this->categories->findForTenant($tenantId, $categoryId);

        foreach ($brandIds as $brandId) {
            $this->brands->findForTenant($tenantId, (int) $brandId);
        }

        $this->categories->syncBrands($category, array_map('intval', $brandIds), $tenantId);

        return $category->fresh(['brands', 'subCategories']);
    }

    public function delete(User $user, int $categoryId): void
    {
        $category = $this->categories->findForTenant($this->tenantId($user), $categoryId);

        if ($this->categories->isUsedByProducts($category)) {
            throw new UnprocessableEntityHttpException('Cannot delete a category that is already used by products.');
        }

        $this->categories->delete($category);
    }

    private function tenantId(User $user): int
    {
        if ($user->tenant_id === null) {
            throw new AccessDeniedHttpException('Authenticated user is not linked to a tenant/store.');
        }

        return (int) $user->tenant_id;
    }

    private function uniqueSlug(int $tenantId, ?string $slug, string $name, ?int $ignoreCategoryId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name) ?: 'category';
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->categories->slugExistsForTenant($tenantId, $candidate, $ignoreCategoryId)) {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
