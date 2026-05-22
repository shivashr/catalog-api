<?php

namespace App\Policies;

use App\Models\ProductCoupon;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductCouponPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canManageTenantCoupons($user, ['owner', 'admin', 'staff']);
    }

    public function view(User $user, ProductCoupon $productCoupon): Response
    {
        $baseAccess = $this->canManageTenantCoupons($user, ['owner', 'admin', 'staff']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $productCoupon->tenant_id) {
            return Response::deny('You do not have permission to access this coupon.');
        }

        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->canManageTenantCoupons($user, ['owner', 'admin']);
    }

    public function update(User $user, ProductCoupon $productCoupon): Response
    {
        $baseAccess = $this->canManageTenantCoupons($user, ['owner', 'admin']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $productCoupon->tenant_id) {
            return Response::deny('You do not have permission to modify this coupon.');
        }

        return Response::allow();
    }

    public function delete(User $user, ProductCoupon $productCoupon): Response
    {
        return $this->update($user, $productCoupon);
    }

    private function canManageTenantCoupons(User $user, array $roles): Response
    {
        if ($user->status !== 'active') {
            return Response::deny('Inactive users cannot access coupon management.');
        }

        if ($user->tenant_id === null) {
            return Response::deny('Authenticated user is not linked to a tenant/store.');
        }

        if (! in_array($user->role, $roles, true)) {
            return Response::deny('This action is unauthorized.');
        }

        return Response::allow();
    }
}

