<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxonomyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_crud_taxonomy_and_assign_brands_to_categories(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Taxonomy Store',
            'slug' => 'taxonomy-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $categoryResponse = $this->postJson('/api/v1/categories', [
            'name' => 'Electronics',
            'status' => 'active',
        ]);

        $categoryResponse->assertCreated()
            ->assertJsonPath('data.name', 'Electronics');

        $categoryId = $categoryResponse->json('data.id');

        $brandResponse = $this->postJson('/api/v1/brands', [
            'name' => 'Acme',
            'status' => 'active',
        ]);

        $brandResponse->assertCreated()
            ->assertJsonPath('data.name', 'Acme');

        $brandId = $brandResponse->json('data.id');

        $subCategoryResponse = $this->postJson('/api/v1/sub-categories', [
            'category_id' => $categoryId,
            'name' => 'Headphones',
            'status' => 'active',
        ]);

        $subCategoryResponse->assertCreated()
            ->assertJsonPath('data.category_id', $categoryId)
            ->assertJsonPath('data.name', 'Headphones');

        $syncResponse = $this->putJson("/api/v1/categories/{$categoryId}/brands", [
            'brand_ids' => [$brandId],
        ]);

        $syncResponse->assertOk()
            ->assertJsonPath('data.brands.0.id', $brandId);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $categoryId);

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonPath('data.0.id', $brandId);

        $this->getJson('/api/v1/sub-categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Headphones');

        $this->patchJson("/api/v1/categories/{$categoryId}", [
            'name' => 'Consumer Electronics',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Consumer Electronics');

        $this->patchJson("/api/v1/brands/{$brandId}", [
            'name' => 'Acme Pro',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Pro');

        $subCategoryId = $subCategoryResponse->json('data.id');

        $this->patchJson("/api/v1/sub-categories/{$subCategoryId}", [
            'name' => 'Wireless Headphones',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Wireless Headphones');
    }

    public function test_option_apis_return_active_taxonomy_for_selected_category(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Option Store',
            'slug' => 'option-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mobiles',
            'slug' => 'mobiles',
            'status' => 'active',
        ]);

        $inactiveCategory = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Inactive Category',
            'slug' => 'inactive-category',
            'status' => 'inactive',
        ]);

        $brand = Brand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'MobileMax',
            'slug' => 'mobilemax',
            'status' => 'active',
        ]);

        $inactiveBrand = Brand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'OldBrand',
            'slug' => 'oldbrand',
            'status' => 'inactive',
        ]);

        $category->brands()->sync([
            $brand->id => ['tenant_id' => $tenant->id],
            $inactiveBrand->id => ['tenant_id' => $tenant->id],
        ]);

        $subCategory = SubCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'status' => 'active',
        ]);

        SubCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Feature Phones',
            'slug' => 'feature-phones',
            'status' => 'inactive',
        ]);

        $this->getJson('/api/v1/options/categories/active')
            ->assertOk()
            ->assertJsonMissing(['id' => $inactiveCategory->id])
            ->assertJsonFragment(['id' => $category->id, 'name' => 'Mobiles']);

        $this->getJson("/api/v1/options/categories/{$category->id}/brands/active")
            ->assertOk()
            ->assertJsonMissing(['id' => $inactiveBrand->id])
            ->assertJsonFragment(['id' => $brand->id, 'name' => 'MobileMax']);

        $this->getJson("/api/v1/options/categories/{$category->id}/sub-categories/active")
            ->assertOk()
            ->assertJsonFragment(['id' => $subCategory->id, 'name' => 'Smartphones'])
            ->assertJsonMissing(['name' => 'Feature Phones']);
    }

    public function test_cannot_delete_taxonomy_records_used_by_products(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Guard Store',
            'slug' => 'guard-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Computers',
            'slug' => 'computers',
            'status' => 'active',
        ]);

        $brand = Brand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'ComputeX',
            'slug' => 'computex',
            'status' => 'active',
        ]);

        $category->brands()->sync([
            $brand->id => ['tenant_id' => $tenant->id],
        ]);

        $subCategory = SubCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Laptops',
            'slug' => 'laptops',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/products', [
            'status' => 'draft',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sub_category_id' => $subCategory->id,
        ])
            ->assertCreated();

        $this->deleteJson("/api/v1/categories/{$category->id}")
            ->assertUnprocessable();

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertUnprocessable();

        $this->deleteJson("/api/v1/sub-categories/{$subCategory->id}")
            ->assertUnprocessable();
    }

    public function test_draft_product_update_rejects_invalid_taxonomy_relations(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Draft Update Store',
            'slug' => 'draft-update-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $mobile = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mobile & Tablets',
            'slug' => 'draft-update-mobile-tablets',
            'status' => 'active',
        ]);

        $fashion = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Fashion',
            'slug' => 'draft-update-fashion',
            'status' => 'active',
        ]);

        $samsung = Brand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Samsung',
            'slug' => 'draft-update-samsung',
            'status' => 'active',
        ]);

        $nike = Brand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Nike',
            'slug' => 'draft-update-nike',
            'status' => 'active',
        ]);

        $mobile->brands()->sync([
            $samsung->id => ['tenant_id' => $tenant->id],
        ]);

        $fashion->brands()->sync([
            $nike->id => ['tenant_id' => $tenant->id],
        ]);

        $smartphones = SubCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $mobile->id,
            'name' => 'Smartphones',
            'slug' => 'draft-update-smartphones',
            'status' => 'active',
        ]);

        $shoes = SubCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $fashion->id,
            'name' => 'Shoes',
            'slug' => 'draft-update-shoes',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $mobile->id,
            'brand_id' => $samsung->id,
            'sub_category_id' => $smartphones->id,
            'name' => 'Draft Product',
            'slug' => 'draft-product',
            'description' => '',
            'selling_price' => 0,
            'stock_quantity' => 0,
            'sku' => 'DRAFT-UPD-001',
            'status' => 'draft',
        ]);

        $this->patchJson("/api/v1/products/{$product->id}", [
            'brand_id' => $nike->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_id']);

        $this->patchJson("/api/v1/products/{$product->id}", [
            'sub_category_id' => $shoes->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sub_category_id']);
    }
}
