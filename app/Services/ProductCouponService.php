<?php

namespace App\Services;

use App\Models\ProductCoupon;
use App\Models\User;
use App\Repositories\ProductCouponRepository;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ProductCouponService
{
    public function __construct(
        private readonly ProductCouponRepository $coupons,
        private readonly ProductRepository $products,
    ) {
    }

    public function list(User $user, string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->coupons->paginateForTenant($this->tenantId($user), $status, $perPage);
    }

    public function show(User $user, int $couponId): ProductCoupon
    {
        return $this->coupons->findForTenant($this->tenantId($user), $couponId);
    }

    public function create(User $user, array $data): ProductCoupon
    {
        $tenantId = $this->tenantId($user);

        $this->products->findForTenant($tenantId, (int) $data['product_id']);

        if ($this->coupons->codeExistsForTenant($tenantId, $data['code'])) {
            throw new UnprocessableEntityHttpException('Coupon code already exists for this tenant.');
        }

        $data['tenant_id'] = $tenantId;
        $data['code'] = mb_strtoupper(trim((string) $data['code']));
        $data['status'] = $data['status'] ?? 'active';
        $data['exchange_only_on_return'] = true;

        return $this->coupons->create($data)->load('product');
    }

    public function update(User $user, int $couponId, array $data): ProductCoupon
    {
        $tenantId = $this->tenantId($user);
        $coupon = $this->coupons->findForTenant($tenantId, $couponId);

        if (array_key_exists('product_id', $data)) {
            $this->products->findForTenant($tenantId, (int) $data['product_id']);
        }

        if (array_key_exists('code', $data)) {
            if ($this->coupons->codeExistsForTenant($tenantId, $data['code'], $coupon->id)) {
                throw new UnprocessableEntityHttpException('Coupon code already exists for this tenant.');
            }

            $data['code'] = mb_strtoupper(trim((string) $data['code']));
        }

        if (array_key_exists('max_uses', $data) && (int) $data['max_uses'] < (int) $coupon->used_count) {
            throw new UnprocessableEntityHttpException('Max uses cannot be lower than already used count.');
        }

        return $this->coupons->update($coupon, $data)->fresh('product');
    }

    public function delete(User $user, int $couponId): void
    {
        $coupon = $this->coupons->findForTenant($this->tenantId($user), $couponId);
        $this->coupons->delete($coupon);
    }

    /**
     * @return array<string, mixed>
     */
    public function redeem(User $user, int $couponId, array $data): array
    {
        return DB::transaction(function () use ($user, $couponId, $data): array {
            $tenantId = $this->tenantId($user);
            $coupon = ProductCoupon::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($couponId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($coupon->status !== 'active') {
                throw new UnprocessableEntityHttpException('Coupon is disabled.');
            }

            if (Carbon::parse($coupon->expiry_date)->isBefore(Carbon::today())) {
                throw new UnprocessableEntityHttpException('Coupon has expired.');
            }

            if ((int) $coupon->used_count >= (int) $coupon->max_uses) {
                throw new UnprocessableEntityHttpException('Coupon has reached max uses.');
            }

            if ((int) $coupon->product_id !== (int) $data['product_id']) {
                throw new UnprocessableEntityHttpException('Coupon is not linked to the provided product.');
            }

            $orderTotal = (float) $data['order_total'];

            if ($orderTotal < (float) $coupon->min_order) {
                throw new UnprocessableEntityHttpException('Order total is below coupon minimum order.');
            }

            $usage = $this->coupons->createUsage([
                'tenant_id' => $tenantId,
                'product_coupon_id' => $coupon->id,
                'product_id' => $coupon->product_id,
                'order_reference' => (string) $data['order_reference'],
                'customer_reference' => $data['customer_reference'] ?? null,
                'quantity' => (int) ($data['quantity'] ?? 1),
                'redeemed_at' => Carbon::now(),
            ]);

            $coupon->increment('used_count');

            return [
                'coupon' => $coupon->fresh('product'),
                'usage_id' => $usage->id,
                'return_policy' => [
                    'can_return' => false,
                    'can_exchange' => true,
                    'reason' => 'coupon_exchange_only',
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function checkReturnEligibility(User $user, array $data): array
    {
        $tenantId = $this->tenantId($user);
        $usage = $this->coupons->findUsageForReturnCheck(
            $tenantId,
            (int) $data['product_id'],
            (string) $data['order_reference'],
        );

        if (! $usage || ! $usage->productCoupon || ! $usage->productCoupon->exchange_only_on_return) {
            return [
                'can_return' => true,
                'can_exchange' => true,
                'reason' => null,
            ];
        }

        return [
            'can_return' => false,
            'can_exchange' => true,
            'reason' => 'coupon_exchange_only',
            'coupon_code' => $usage->productCoupon->code,
            'coupon_id' => $usage->productCoupon->id,
        ];
    }

    private function tenantId(User $user): int
    {
        if ($user->tenant_id === null) {
            throw new AccessDeniedHttpException('Authenticated user is not linked to a tenant/store.');
        }

        return (int) $user->tenant_id;
    }
}
