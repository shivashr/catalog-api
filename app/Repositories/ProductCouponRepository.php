<?php

namespace App\Repositories;

use App\Models\ProductCoupon;
use App\Models\ProductCouponUsage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ProductCouponRepository
{
    public function paginateForTenant(int $tenantId, string $status = 'all', int $perPage = 15): LengthAwarePaginator
    {
        $today = Carbon::today()->toDateString();

        return ProductCoupon::query()
            ->with('product')
            ->where('tenant_id', $tenantId)
            ->when($status === 'active', function ($query) use ($today): void {
                $query->where('status', 'active')
                    ->whereDate('expiry_date', '>=', $today);
            })
            ->when($status === 'disabled', fn ($query) => $query->where('status', 'disabled'))
            ->when($status === 'expired', fn ($query) => $query->whereDate('expiry_date', '<', $today))
            ->latest()
            ->paginate($perPage);
    }

    public function findForTenant(int $tenantId, int $couponId): ProductCoupon
    {
        return ProductCoupon::query()
            ->with('product')
            ->where('tenant_id', $tenantId)
            ->findOrFail($couponId);
    }

    public function codeExistsForTenant(int $tenantId, string $code, ?int $ignoreCouponId = null): bool
    {
        return ProductCoupon::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->when($ignoreCouponId, fn ($query) => $query->whereKeyNot($ignoreCouponId))
            ->exists();
    }

    public function create(array $data): ProductCoupon
    {
        return ProductCoupon::query()->create($data);
    }

    public function update(ProductCoupon $productCoupon, array $data): ProductCoupon
    {
        $productCoupon->update($data);

        return $productCoupon;
    }

    public function delete(ProductCoupon $productCoupon): void
    {
        $productCoupon->delete();
    }

    public function createUsage(array $data): ProductCouponUsage
    {
        return ProductCouponUsage::query()->create($data);
    }

    public function findUsageForReturnCheck(
        int $tenantId,
        int $productId,
        string $orderReference
    ): ?ProductCouponUsage {
        return ProductCouponUsage::query()
            ->with('productCoupon')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('order_reference', $orderReference)
            ->latest('id')
            ->first();
    }
}

