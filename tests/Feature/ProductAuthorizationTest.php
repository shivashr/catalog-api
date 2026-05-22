<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_product(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Alpha Store',
            'slug' => 'alpha-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'brand' => $brand, 'subCategory' => $subCategory] = $this->createTaxonomy($tenant, 'shoes');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/products', $this->activePayload($category->id, $brand->id, $subCategory->id, 'TRAIL-001'), [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', [
            'name' => 'Trail Running Shoe',
            'tenant_id' => $tenant->id,
            'sku' => 'TRAIL-001',
        ]);
    }

    public function test_customer_cannot_create_product(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Beta Store',
            'slug' => 'beta-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'brand' => $brand, 'subCategory' => $subCategory] = $this->createTaxonomy($tenant, 'beta-shoes');

        $customer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->post('/api/v1/products', $this->activePayload($category->id, $brand->id, $subCategory->id, 'TRAIL-001'), [
            'Accept' => 'application/json',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_tenant_id_comes_from_authenticated_user(): void
    {
        Storage::fake('public');

        $tenantA = Tenant::query()->create([
            'name' => 'Gamma Store',
            'slug' => 'gamma-store',
            'status' => 'active',
        ]);

        ['category' => $categoryA, 'brand' => $brandA, 'subCategory' => $subCategoryA] = $this->createTaxonomy($tenantA, 'gamma-shoes');

        $tenantB = Tenant::query()->create([
            'name' => 'Delta Store',
            'slug' => 'delta-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $payload = $this->activePayload($categoryA->id, $brandA->id, $subCategoryA->id, 'TRAIL-002');
        $payload['tenant_id'] = $tenantB->id;

        $response = $this->post('/api/v1/products', $payload, [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $createdId = $response->json('data.id');
        $createdProduct = Product::query()->findOrFail($createdId);

        $this->assertSame($tenantA->id, $createdProduct->tenant_id);
    }

    public function test_user_cannot_access_another_tenants_product(): void
    {
        $tenantA = Tenant::query()->create([
            'name' => 'Omega Store',
            'slug' => 'omega-store',
            'status' => 'active',
        ]);

        $tenantB = Tenant::query()->create([
            'name' => 'Sigma Store',
            'slug' => 'sigma-store',
            'status' => 'active',
        ]);

        $ownerA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'External Product',
            'slug' => 'external-product',
            'description' => 'Not owned by tenant A.',
            'selling_price' => 10.00,
            'stock_quantity' => 5,
            'sku' => 'EXT-001',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ownerA);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertForbidden();
    }

    public function test_user_without_tenant_gets_clean_json_error(): void
    {
        $adminWithoutTenant = User::factory()->create([
            'tenant_id' => null,
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($adminWithoutTenant);

        $this->getJson('/api/v1/products')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Authenticated user is not linked to a tenant/store.',
            ]);
    }

    public function test_draft_product_can_be_saved_incomplete(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Draft Store',
            'slug' => 'draft-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/products', [
            'status' => 'draft',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.tenant_id', $tenant->id);
    }

    public function test_active_product_without_category_or_images_fails_validation(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Validation Store',
            'slug' => 'validation-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/products', [
            'status' => 'active',
            'name' => 'Missing Publish Data',
            'condition' => 'new',
            'description' => 'No category and no image.',
            'selling_price' => 49.99,
            'stock_quantity' => 5,
            'sku' => 'MISSING-PUBLISH-DATA',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'brand_id', 'sub_category_id', 'images']);
    }

    public function test_active_product_with_category_and_image_succeeds(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Publish Store',
            'slug' => 'publish-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'brand' => $brand, 'subCategory' => $subCategory] = $this->createTaxonomy($tenant, 'publish-electronics');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/products', $this->activePayload($category->id, $brand->id, $subCategory->id, 'ACTIVE-IMAGE-001'), [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.images.0.is_primary', true);
    }

    public function test_active_product_fails_when_brand_is_not_linked_to_category(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Brand Link Store',
            'slug' => 'brand-link-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'subCategory' => $subCategory] = $this->createTaxonomy($tenant, 'brand-link');
        $unlinkedBrand = Brand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Unlinked Brand',
            'slug' => 'unlinked-brand',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'brand_id' => $unlinkedBrand->id,
            'sub_category_id' => $subCategory->id,
            'name' => 'Broken Taxonomy Product',
            'condition' => 'new',
            'description' => 'Invalid brand link.',
            'selling_price' => 99.99,
            'stock_quantity' => 2,
            'sku' => 'BROKEN-LINK-001',
            'status' => 'active',
            'images' => [
                UploadedFile::fake()->image('product.jpg')->size(500),
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_id']);
    }

    public function test_active_product_fails_when_sub_category_does_not_belong_to_category(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Subcategory Rule Store',
            'slug' => 'subcategory-rule-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'brand' => $brand] = $this->createTaxonomy($tenant, 'sub-rule-one');
        ['subCategory' => $otherSubCategory] = $this->createTaxonomy($tenant, 'sub-rule-two');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sub_category_id' => $otherSubCategory->id,
            'name' => 'Wrong Subcategory Product',
            'condition' => 'new',
            'description' => 'Sub-category/category mismatch.',
            'selling_price' => 149.99,
            'stock_quantity' => 3,
            'sku' => 'BROKEN-SUB-001',
            'status' => 'active',
            'images' => [
                UploadedFile::fake()->image('product.jpg')->size(500),
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sub_category_id']);
    }

    public function test_status_update_to_active_requires_existing_image(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Status Store',
            'slug' => 'status-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'brand' => $brand, 'subCategory' => $subCategory] = $this->createTaxonomy($tenant, 'status-electronics');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sub_category_id' => $subCategory->id,
            'name' => 'Draft Without Image',
            'slug' => 'draft-without-image',
            'condition' => 'new',
            'description' => 'Complete except image.',
            'selling_price' => 25.00,
            'stock_quantity' => 3,
            'sku' => 'DRAFT-NO-IMAGE',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/products/{$product->id}/status", [
            'status' => 'active',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images']);
    }

    public function test_full_product_payload_fields_are_persisted_and_returned(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Full Field Store',
            'slug' => 'full-field-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'brand' => $brand, 'subCategory' => $subCategory] = $this->createTaxonomy($tenant, 'electronics');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sub_category_id' => $subCategory->id,
            'name' => 'Noise Cancelling Headphones',
            'slug' => 'noise-cancelling-headphones',
            'model_number' => 'NC-500',
            'condition' => 'new',
            'description' => 'Over-ear wireless ANC headphones.',
            'tags' => 'audio, wireless, anc',
            'selling_price' => 199.99,
            'mrp_price' => 249.99,
            'stock_quantity' => 50,
            'low_stock_alert' => 10,
            'sku' => 'NC-500',
            'barcode' => '123456789012',
            'cost_price' => 130.00,
            'tax_rate' => 13.00,
            'weight' => 0.35,
            'length' => 18.5,
            'width' => 16.2,
            'height' => 8.4,
            'shipping_class' => 'Standard',
            'free_shipping' => true,
            'status' => 'active',
            'images' => [
                UploadedFile::fake()->image('headphones.jpg')->size(500),
            ],
            'seo' => [
                'meta_title' => 'Noise Cancelling Headphones',
                'meta_description' => 'Premium wireless ANC headphones.',
            ],
            'variants' => [
                'size' => ['One Size'],
                'color' => ['Black', 'Blue'],
                'material' => ['Plastic', 'Metal'],
            ],
            'specifications' => [
                ['attribute' => 'Battery Life', 'value' => '40 hours'],
                ['attribute' => 'Bluetooth', 'value' => '5.3'],
            ],
            'warranty' => [
                'warranty_duration' => 12,
                'warranty_unit' => 'months',
                'warranty_by' => 'seller',
                'return_window' => '7 Days',
                'return_type' => 'exchange_only',
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.brand_id', $brand->id)
            ->assertJsonPath('data.sub_category_id', $subCategory->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonPath('data.sub_category.id', $subCategory->id)
            ->assertJsonPath('data.tax_rate', '13.00')
            ->assertJsonPath('data.model_number', 'NC-500')
            ->assertJsonPath('data.free_shipping', true)
            ->assertJsonPath('data.variants.color.0', 'Black')
            ->assertJsonPath('data.seo.meta_title', 'Noise Cancelling Headphones');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'name' => 'Noise Cancelling Headphones',
            'tax_rate' => 13,
            'shipping_class' => 'Standard',
        ]);
    }

    public function test_product_full_crud_status_and_images_work(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Crud Store',
            'slug' => 'crud-store',
            'status' => 'active',
        ]);

        ['category' => $category, 'brand' => $brand, 'subCategory' => $subCategory] = $this->createTaxonomy($tenant, 'crud-electronics');

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $createResponse = $this->post('/api/v1/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sub_category_id' => $subCategory->id,
            'name' => 'Full CRUD Product',
            'description' => 'Verifies create, read, update, status, image upload, image delete, and product delete.',
            'condition' => 'new',
            'selling_price' => 299.99,
            'stock_quantity' => 15,
            'sku' => 'FULL-CRUD-001',
            'status' => 'active',
            'images' => [
                UploadedFile::fake()->image('primary.jpg')->size(500),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.images.0.is_primary', true);

        $productId = $createResponse->json('data.id');
        $firstImagePath = $createResponse->json('data.images.0.image_path');

        Storage::disk('public')->assertExists($firstImagePath);

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Full CRUD Product');

        $updateResponse = $this->put("/api/v1/products/{$productId}", [
            'name' => 'Updated CRUD Product',
            'sku' => 'FULL-CRUD-002',
            'selling_price' => 249.99,
            'images' => [
                UploadedFile::fake()->image('gallery.png')->size(500),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Updated CRUD Product')
            ->assertJsonPath('data.sku', 'FULL-CRUD-002');
        $this->assertCount(2, $updateResponse->json('data.images'));

        $this->patchJson("/api/v1/products/{$productId}/status", [
            'status' => 'draft',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->deleteJson("/api/v1/products/{$productId}")
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $productId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function activePayload(int $categoryId, int $brandId, int $subCategoryId, string $sku): array
    {
        return [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'sub_category_id' => $subCategoryId,
            'name' => 'Trail Running Shoe',
            'condition' => 'new',
            'description' => 'Balanced cushion and grip for mixed surfaces.',
            'selling_price' => 89.99,
            'stock_quantity' => 20,
            'sku' => $sku,
            'status' => 'active',
            'images' => [
                UploadedFile::fake()->image('product.jpg')->size(500),
            ],
        ];
    }

    /**
     * @return array{category: Category, brand: Brand, subCategory: SubCategory}
     */
    private function createTaxonomy(Tenant $tenant, string $slugPrefix): array
    {
        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst(str_replace('-', ' ', $slugPrefix)).' Category',
            'slug' => $slugPrefix.'-category',
            'status' => 'active',
        ]);

        $brand = Brand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst(str_replace('-', ' ', $slugPrefix)).' Brand',
            'slug' => $slugPrefix.'-brand',
            'status' => 'active',
        ]);

        $subCategory = SubCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => ucfirst(str_replace('-', ' ', $slugPrefix)).' SubCategory',
            'slug' => $slugPrefix.'-sub-category',
            'status' => 'active',
        ]);

        $category->brands()->sync([
            $brand->id => ['tenant_id' => $tenant->id],
        ]);

        return [
            'category' => $category,
            'brand' => $brand,
            'subCategory' => $subCategory,
        ];
    }
}
