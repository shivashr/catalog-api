<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BrandService
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->brands->paginateForTenant($this->tenantId($user), $perPage);
    }

    public function optionsByCategory(User $user, int $categoryId): Collection
    {
        $tenantId = $this->tenantId($user);
        $this->categories->findForTenant($tenantId, $categoryId);

        return $this->brands->activeForCategory($tenantId, $categoryId);
    }

    public function show(User $user, int $brandId): Brand
    {
        return $this->brands->findForTenant($this->tenantId($user), $brandId);
    }

    public function create(User $user, array $data): Brand
    {
        $tenantId = $this->tenantId($user);
        $data['tenant_id'] = $tenantId;
        $data['slug'] = $this->uniqueSlug($tenantId, $data['slug'] ?? null, $data['name']);
        $data['status'] = $data['status'] ?? 'active';

        return $this->brands->create($data)->load('categories');
    }

    public function update(User $user, int $brandId, array $data): Brand
    {
        $tenantId = $this->tenantId($user);
        $brand = $this->brands->findForTenant($tenantId, $brandId);

        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug(
                $tenantId,
                $data['slug'] ?? $brand->slug,
                $data['name'] ?? $brand->name,
                $brand->id
            );
        }

        return $this->brands->update($brand, $data)->load('categories');
    }

    public function delete(User $user, int $brandId): void
    {
        $brand = $this->brands->findForTenant($this->tenantId($user), $brandId);

        if ($this->brands->isUsedByProducts($brand)) {
            throw new UnprocessableEntityHttpException('Cannot delete a brand that is already used by products.');
        }

        $this->brands->delete($brand);
    }

    private function tenantId(User $user): int
    {
        if ($user->tenant_id === null) {
            throw new AccessDeniedHttpException('Authenticated user is not linked to a tenant/store.');
        }

        return (int) $user->tenant_id;
    }

    private function uniqueSlug(int $tenantId, ?string $slug, string $name, ?int $ignoreBrandId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name) ?: 'brand';
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->brands->slugExistsForTenant($tenantId, $candidate, $ignoreBrandId)) {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
