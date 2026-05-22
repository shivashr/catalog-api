<?php

namespace App\Policies;

use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubCategoryPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canManageTenantTaxonomy($user, ['owner', 'admin', 'staff']);
    }

    public function view(User $user, SubCategory $subCategory): Response
    {
        $baseAccess = $this->canManageTenantTaxonomy($user, ['owner', 'admin', 'staff']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $subCategory->tenant_id) {
            return Response::deny('You do not have permission to access this sub-category.');
        }

        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->canManageTenantTaxonomy($user, ['owner', 'admin']);
    }

    public function update(User $user, SubCategory $subCategory): Response
    {
        $baseAccess = $this->canManageTenantTaxonomy($user, ['owner', 'admin']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $subCategory->tenant_id) {
            return Response::deny('You do not have permission to modify this sub-category.');
        }

        return Response::allow();
    }

    public function delete(User $user, SubCategory $subCategory): Response
    {
        return $this->update($user, $subCategory);
    }

    private function canManageTenantTaxonomy(User $user, array $roles): Response
    {
        if ($user->status !== 'active') {
            return Response::deny('Inactive users cannot access taxonomy management.');
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
