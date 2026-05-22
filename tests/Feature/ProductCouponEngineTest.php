<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCouponEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_crud_product_coupon(): void
    {
        [$tenant, $owner, $product] = $this->createTenantOwnerAndProduct();
        Sanctum::actingAs($owner);

        $createResponse = $this->postJson('/api/v1/product-coupons', [
            'product_id' => $product->id,
            'code' => 'save20',
            'discount_type' => 'fixed_amount',
            'value' => 100,
            'min_order' => 500,
            'max_uses' => 100,
            'expiry_date' => Carbon::today()->addDays(10)->format('Y-m-d'),
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.code', 'SAVE20')
            ->assertJsonPath('data.tab_status', 'active');

        $couponId = (int) $createResponse->json('data.id');

        $this->getJson('/api/v1/product-coupons?status=active')
            ->assertOk()
            ->assertJsonFragment(['id' => $couponId, 'code' => 'SAVE20']);

        $this->patchJson("/api/v1/product-coupons/{$couponId}", [
            'status' => 'disabled',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.tab_status', 'disabled');

        $this->getJson('/api/v1/product-coupons?status=disabled')
            ->assertOk()
            ->assertJsonFragment(['id' => $couponId, 'code' => 'SAVE20']);

        $this->deleteJson("/api/v1/product-coupons/{$couponId}")
            ->assertNoContent();

        $this->assertSoftDeleted('product_coupons', [
            'id' => $couponId,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_coupon_redemption_makes_product_exchange_only_for_return(): void
    {
        [, $owner, $product] = $this->createTenantOwnerAndProduct();
        Sanctum::actingAs($owner);

        $couponId = (int) $this->postJson('/api/v1/product-coupons', [
            'product_id' => $product->id,
            'code' => 'RETURNLOCK',
            'discount_type' => 'percentage',
            'value' => 10,
            'min_order' => 100,
            'max_uses' => 2,
            'expiry_date' => Carbon::today()->addDays(5)->format('Y-m-d'),
        ])->json('data.id');

        $redeemResponse = $this->postJson("/api/v1/product-coupons/{$couponId}/redeem", [
            'product_id' => $product->id,
            'order_reference' => 'ORDER-1001',
            'customer_reference' => 'CUST-1',
            'order_total' => 500,
            'quantity' => 1,
        ]);

        $redeemResponse->assertOk()
            ->assertJsonPath('data.return_policy.can_return', false)
            ->assertJsonPath('data.return_policy.can_exchange', true)
            ->assertJsonPath('data.return_policy.reason', 'coupon_exchange_only')
            ->assertJsonPath('data.coupon.used_count', 1);

        $this->postJson('/api/v1/product-coupons/check-return-eligibility', [
            'product_id' => $product->id,
            'order_reference' => 'ORDER-1001',
        ])
            ->assertOk()
            ->assertJsonPath('data.can_return', false)
            ->assertJsonPath('data.can_exchange', true)
            ->assertJsonPath('data.reason', 'coupon_exchange_only')
            ->assertJsonPath('data.coupon_code', 'RETURNLOCK');

        $this->postJson('/api/v1/product-coupons/check-return-eligibility', [
            'product_id' => $product->id,
            'order_reference' => 'ORDER-2002',
        ])
            ->assertOk()
            ->assertJsonPath('data.can_return', true)
            ->assertJsonPath('data.reason', null);
    }

    public function test_coupon_respects_max_uses_on_redeem(): void
    {
        [, $owner, $product] = $this->createTenantOwnerAndProduct();
        Sanctum::actingAs($owner);

        $couponId = (int) $this->postJson('/api/v1/product-coupons', [
            'product_id' => $product->id,
            'code' => 'ONEUSE',
            'discount_type' => 'fixed_amount',
            'value' => 50,
            'min_order' => 100,
            'max_uses' => 1,
            'expiry_date' => Carbon::today()->addDays(1)->format('Y-m-d'),
        ])->json('data.id');

        $this->postJson("/api/v1/product-coupons/{$couponId}/redeem", [
            'product_id' => $product->id,
            'order_reference' => 'ORDER-A',
            'order_total' => 150,
        ])->assertOk();

        $this->postJson("/api/v1/product-coupons/{$couponId}/redeem", [
            'product_id' => $product->id,
            'order_reference' => 'ORDER-B',
            'order_total' => 150,
        ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Coupon has reached max uses.',
            ]);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Product}
     */
    private function createTenantOwnerAndProduct(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Coupon Store',
            'slug' => 'coupon-store',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Coupon Product',
            'slug' => 'coupon-product',
            'description' => 'Coupon product',
            'selling_price' => 1200,
            'stock_quantity' => 50,
            'sku' => 'CP-001',
            'status' => 'active',
        ]);

        return [$tenant, $owner, $product];
    }
}

