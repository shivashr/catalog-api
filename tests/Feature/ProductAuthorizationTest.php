<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Category;
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

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Shoes',
            'slug' => 'shoes',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/products', $this->activePayload($category->id, 'TRAIL-001'), [
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

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Shoes',
            'slug' => 'beta-shoes',
            'status' => 'active',
        ]);

        $customer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->post('/api/v1/products', $this->activePayload($category->id, 'TRAIL-001'), [
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

        $categoryA = Category::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Shoes',
            'slug' => 'gamma-shoes',
            'status' => 'active',
        ]);

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

        $payload = $this->activePayload($categoryA->id, 'TRAIL-002');
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

    public function test_active_product_without_category_or_image_fails_validation(): void
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
            ->assertJsonValidationErrors(['category_id', 'images']);
    }

    public function test_active_product_with_category_and_image_succeeds(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Publish Store',
            'slug' => 'publish-store',
            'status' => 'active',
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Electronics',
            'slug' => 'publish-electronics',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/products', $this->activePayload($category->id, 'ACTIVE-IMAGE-001'), [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.images.0.is_primary', true);
    }

    public function test_status_update_to_active_requires_existing_image(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Status Store',
            'slug' => 'status-store',
            'status' => 'active',
        ]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Electronics',
            'slug' => 'status-electronics',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
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

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/products', [
            'category_id' => $category->id,
            'name' => 'Noise Cancelling Headphones',
            'slug' => 'noise-cancelling-headphones',
            'brand' => 'Tukaatu',
            'sub_category' => 'Headphones',
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
            ->assertJsonPath('data.tax_rate', '13.00')
            ->assertJsonPath('data.sub_category', 'Headphones')
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

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Electronics',
            'slug' => 'crud-electronics',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $createResponse = $this->post('/api/v1/products', [
            'category_id' => $category->id,
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
        $firstImageId = $createResponse->json('data.images.0.id');
        $firstImagePath = $createResponse->json('data.images.0.image_path');

        Storage::disk('public')->assertExists($firstImagePath);

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Full CRUD Product');

        $this->patchJson("/api/v1/products/{$productId}", [
            'name' => 'Updated CRUD Product',
            'sku' => 'FULL-CRUD-002',
            'selling_price' => 249.99,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated CRUD Product')
            ->assertJsonPath('data.sku', 'FULL-CRUD-002');

        $this->patchJson("/api/v1/products/{$productId}/status", [
            'status' => 'draft',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $imageResponse = $this->post("/api/v1/products/{$productId}/images", [
            'images' => [
                UploadedFile::fake()->image('gallery.png')->size(500),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $imageResponse->assertOk();
        $this->assertCount(2, $imageResponse->json('data.images'));

        $this->deleteJson("/api/v1/products/{$productId}/images/{$firstImageId}")
            ->assertOk();

        Storage::disk('public')->assertMissing($firstImagePath);
        $this->assertDatabaseMissing('product_images', ['id' => $firstImageId]);

        $this->deleteJson("/api/v1/products/{$productId}")
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $productId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function activePayload(int $categoryId, string $sku): array
    {
        return [
            'category_id' => $categoryId,
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
}
