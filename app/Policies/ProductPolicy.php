<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canManageTenantProducts($user, ['owner', 'admin', 'staff']);
    }

    public function view(User $user, Product $product): Response
    {
        $baseAccess = $this->canManageTenantProducts($user, ['owner', 'admin', 'staff']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $product->tenant_id) {
            return Response::deny('You do not have permission to access this product.');
        }

        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->canManageTenantProducts($user, ['owner', 'admin']);
    }

    public function update(User $user, Product $product): Response
    {
        $baseAccess = $this->canManageTenantProducts($user, ['owner', 'admin']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $product->tenant_id) {
            return Response::deny('You do not have permission to modify this product.');
        }

        return Response::allow();
    }

    public function delete(User $user, Product $product): Response
    {
        return $this->update($user, $product);
    }

    private function canManageTenantProducts(User $user, array $roles): Response
    {
        if ($user->status !== 'active') {
            return Response::deny('Inactive users cannot access product management.');
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
