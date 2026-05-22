<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxonomyOptionResource;
use App\Models\Category;
use App\Models\User;
use App\Services\BrandService;
use App\Services\CategoryService;
use App\Services\SubCategoryService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxonomyOptionController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly BrandService $brands,
        private readonly SubCategoryService $subCategories,
    ) {
    }

    public function activeCategories(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', Category::class);

        return TaxonomyOptionResource::collection($this->categories->options($user));
    }

    public function activeBrandsByCategory(Request $request, Category $category): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('view', $category);

        return TaxonomyOptionResource::collection($this->brands->optionsByCategory($user, $category->id));
    }

    public function activeSubCategoriesByCategory(Request $request, Category $category): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('view', $category);

        return TaxonomyOptionResource::collection($this->subCategories->optionsByCategory($user, $category->id));
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user;
    }
}
