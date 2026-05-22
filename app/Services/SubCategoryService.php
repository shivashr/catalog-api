<?php

namespace App\Services;

use App\Models\SubCategory;
use App\Models\User;
use App\Repositories\CategoryRepository;
use App\Repositories\SubCategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubCategoryService
{
    public function __construct(
        private readonly SubCategoryRepository $subCategories,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->subCategories->paginateForTenant($this->tenantId($user), $perPage);
    }

    public function optionsByCategory(User $user, int $categoryId): Collection
    {
        $tenantId = $this->tenantId($user);
        $this->categories->findForTenant($tenantId, $categoryId);

        return $this->subCategories->activeForCategory($tenantId, $categoryId);
    }

    public function show(User $user, int $subCategoryId): SubCategory
    {
        return $this->subCategories->findForTenant($this->tenantId($user), $subCategoryId);
    }

    public function create(User $user, array $data): SubCategory
    {
        $tenantId = $this->tenantId($user);
        $this->categories->findForTenant($tenantId, (int) $data['category_id']);

        $data['tenant_id'] = $tenantId;
        $data['slug'] = $this->uniqueSlug($tenantId, $data['slug'] ?? null, $data['name']);
        $data['status'] = $data['status'] ?? 'active';

        return $this->subCategories->create($data)->load('category');
    }

    public function update(User $user, int $subCategoryId, array $data): SubCategory
    {
        $tenantId = $this->tenantId($user);
        $subCategory = $this->subCategories->findForTenant($tenantId, $subCategoryId);

        if (array_key_exists('category_id', $data)) {
            $this->categories->findForTenant($tenantId, (int) $data['category_id']);
        }

        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug(
                $tenantId,
                $data['slug'] ?? $subCategory->slug,
                $data['name'] ?? $subCategory->name,
                $subCategory->id
            );
        }

        return $this->subCategories->update($subCategory, $data)->load('category');
    }

    public function delete(User $user, int $subCategoryId): void
    {
        $subCategory = $this->subCategories->findForTenant($this->tenantId($user), $subCategoryId);

        if ($this->subCategories->isUsedByProducts($subCategory)) {
            throw new UnprocessableEntityHttpException('Cannot delete a sub-category that is already used by products.');
        }

        $this->subCategories->delete($subCategory);
    }

    private function tenantId(User $user): int
    {
        if ($user->tenant_id === null) {
            throw new AccessDeniedHttpException('Authenticated user is not linked to a tenant/store.');
        }

        return (int) $user->tenant_id;
    }

    private function uniqueSlug(int $tenantId, ?string $slug, string $name, ?int $ignoreSubCategoryId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name) ?: 'sub-category';
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->subCategories->slugExistsForTenant($tenantId, $candidate, $ignoreSubCategoryId)) {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
