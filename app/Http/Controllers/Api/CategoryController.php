<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\SyncCategoryBrandsRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', Category::class);

        $categories = $this->categories->list($user, min((int) $request->integer('per_page', 15), 100));

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('create', Category::class);

        $category = $this->categories->create($user, $request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Request $request, Category $category): CategoryResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('view', $category);

        return new CategoryResource($this->categories->show($user, $category->id));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $category);

        return new CategoryResource($this->categories->update($user, $category->id, $request->validated()));
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('delete', $category);

        $this->categories->delete($user, $category->id);

        return response()->json(null, 204);
    }

    public function syncBrands(SyncCategoryBrandsRequest $request, Category $category): CategoryResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $category);

        return new CategoryResource(
            $this->categories->syncBrands($user, $category->id, $request->validated('brand_ids'))
        );
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
