<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canManageTenantTaxonomy($user, ['owner', 'admin', 'staff']);
    }

    public function view(User $user, Category $category): Response
    {
        $baseAccess = $this->canManageTenantTaxonomy($user, ['owner', 'admin', 'staff']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $category->tenant_id) {
            return Response::deny('You do not have permission to access this category.');
        }

        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->canManageTenantTaxonomy($user, ['owner', 'admin']);
    }

    public function update(User $user, Category $category): Response
    {
        $baseAccess = $this->canManageTenantTaxonomy($user, ['owner', 'admin']);

        if ($baseAccess->denied()) {
            return $baseAccess;
        }

        if ((int) $user->tenant_id !== (int) $category->tenant_id) {
            return Response::deny('You do not have permission to modify this category.');
        }

        return Response::allow();
    }

    public function delete(User $user, Category $category): Response
    {
        return $this->update($user, $category);
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
