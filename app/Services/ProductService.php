<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ProductService
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->products->paginateForTenant($this->tenantId($user), $perPage);
    }

    public function show(User $user, int $productId): Product
    {
        return $this->products->findForTenant($this->tenantId($user), $productId);
    }

    public function create(User $user, array $data): Product
    {
        return DB::transaction(function () use ($user, $data): Product {
            $tenantId = $this->tenantId($user);
            $images = Arr::pull($data, 'images', []);
            $variants = Arr::pull($data, 'variants', []);
            $specifications = Arr::pull($data, 'specifications', []);
            $warranty = Arr::pull($data, 'warranty', null);
            $seo = Arr::pull($data, 'seo', []);

            $data = $this->normalizeProductData($data, $seo);
            $data['tenant_id'] = $tenantId;
            $data['name'] = $data['name'] ?? 'Untitled Product';
            $data['description'] = $data['description'] ?? '';
            $data['selling_price'] = $data['selling_price'] ?? 0;
            $data['stock_quantity'] = $data['stock_quantity'] ?? 0;
            $data['sku'] = $data['sku'] ?? $this->draftSku($tenantId);
            $data['status'] = $data['status'] ?? 'draft';
            $data['slug'] = $this->uniqueSlug($tenantId, $data['slug'] ?? null, $data['name']);

            $product = $this->products->create($data);

            $this->syncRelations($product, $variants, $specifications, $warranty);
            $this->storeImages($product, $images);

            return $product->fresh(['images', 'variants', 'specifications', 'warranty', 'category', 'brandModel', 'subCategoryModel']);
        });
    }

    public function update(User $user, int $productId, array $data): Product
    {
        return DB::transaction(function () use ($user, $productId, $data): Product {
            $tenantId = $this->tenantId($user);
            $product = $this->products->findForTenant($tenantId, $productId);

            $images = Arr::pull($data, 'images', null);
            $variants = Arr::pull($data, 'variants', null);
            $specifications = Arr::pull($data, 'specifications', null);
            $warranty = Arr::pull($data, 'warranty', null);
            $seo = Arr::pull($data, 'seo', null);

            $data = $this->normalizeProductData($data, $seo ?? []);

            if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
                $data['slug'] = $this->uniqueSlug(
                    $tenantId,
                    $data['slug'] ?? $product->slug,
                    $data['name'] ?? $product->name,
                    $product->id
                );
            }

            $product = $this->products->update($product, $data);

            if ($variants !== null) {
                $this->products->replaceVariants($product, $this->normalizeVariants($variants));
            }

            if ($specifications !== null) {
                $this->products->replaceSpecifications($product, $this->normalizeSpecifications($specifications));
            }

            if ($warranty !== null) {
                $this->products->upsertWarranty($product, $warranty);
            }

            if ($images !== null) {
                $this->storeImages($product, $images);
            }

            return $product->fresh(['images', 'variants', 'specifications', 'warranty', 'category', 'brandModel', 'subCategoryModel']);
        });
    }

    public function delete(User $user, int $productId): void
    {
        $product = $this->products->findForTenant($this->tenantId($user), $productId);
        $this->products->delete($product);
    }

    public function updateStatus(User $user, int $productId, string $status): Product
    {
        $product = $this->products->findForTenant($this->tenantId($user), $productId);
        $this->products->update($product, ['status' => $status]);

        return $product->fresh(['images', 'variants', 'specifications', 'warranty', 'category', 'brandModel', 'subCategoryModel']);
    }

    private function tenantId(User $user): int
    {
        if ($user->tenant_id === null) {
            throw new AccessDeniedHttpException('Authenticated user is not linked to a tenant/store.');
        }

        return (int) $user->tenant_id;
    }

    private function normalizeProductData(array $data, array $seo = []): array
    {
        if (array_key_exists('seo', $data)) {
            unset($data['seo']);
        }

        if (array_key_exists('tags', $data) && is_string($data['tags'])) {
            $data['tags'] = collect(explode(',', $data['tags']))
                ->map(fn (string $tag): string => trim($tag))
                ->filter()
                ->values()
                ->all();
        }

        if ($seo !== []) {
            $data['meta_title'] = $seo['meta_title'] ?? ($data['meta_title'] ?? null);
            $data['meta_description'] = $seo['meta_description'] ?? ($data['meta_description'] ?? null);
        }

        return $data;
    }

    private function syncRelations(Product $product, array $variants, array $specifications, ?array $warranty): void
    {
        $this->products->replaceVariants($product, $this->normalizeVariants($variants));
        $this->products->replaceSpecifications($product, $this->normalizeSpecifications($specifications));
        $this->products->upsertWarranty($product, $warranty);
    }

    private function normalizeVariants(array $variants): array
    {
        if ($variants === []) {
            return [];
        }

        if (! array_is_list($variants)) {
            return collect($variants)
                ->flatMap(function (mixed $values, string $type) {
                    return collect(is_array($values) ? $values : [])
                        ->map(fn (mixed $value): array => [
                            'type' => $type,
                            'value' => (string) $value,
                        ]);
                })
                ->values()
                ->all();
        }

        return collect($variants)
            ->map(fn (array $variant): array => Arr::only($variant, ['type', 'value']))
            ->values()
            ->all();
    }

    private function normalizeSpecifications(array $specifications): array
    {
        return collect($specifications)
            ->values()
            ->map(function (array $specification, int $index): array {
                return [
                    'attribute' => $specification['attribute'],
                    'value' => $specification['value'],
                    'sort_order' => $specification['sort_order'] ?? $index,
                ];
            })
            ->all();
    }

    private function storeImages(Product $product, array $images): void
    {
        if ($images === []) {
            return;
        }

        $currentCount = $product->images()->count();

        if ($currentCount + count($images) > 12) {
            throw new UnprocessableEntityHttpException('A product can have a maximum of 12 images.');
        }

        $records = collect($images)
            ->values()
            ->map(function (UploadedFile $image, int $index) use ($currentCount): array {
                return [
                    'image_path' => $image->store('products', 'public'),
                    'sort_order' => $currentCount + $index,
                    'is_primary' => $currentCount === 0 && $index === 0,
                ];
            })
            ->all();

        $product->images()->createMany($records);
    }

    private function uniqueSlug(int $tenantId, ?string $slug, string $name, ?int $ignoreProductId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name) ?: 'product';
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->products->slugExistsForTenant($tenantId, $candidate, $ignoreProductId)) {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function draftSku(int $tenantId): string
    {
        do {
            $sku = 'DRAFT-'.Str::upper(Str::random(10));
        } while ($this->products->skuExistsForTenant($tenantId, $sku));

        return $sku;
    }
}
