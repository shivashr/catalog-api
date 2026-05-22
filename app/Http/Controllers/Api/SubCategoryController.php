<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubCategory\StoreSubCategoryRequest;
use App\Http\Requests\SubCategory\UpdateSubCategoryRequest;
use App\Http\Resources\SubCategoryResource;
use App\Models\SubCategory;
use App\Models\User;
use App\Services\SubCategoryService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubCategoryController extends Controller
{
    public function __construct(private readonly SubCategoryService $subCategories)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', SubCategory::class);

        $subCategories = $this->subCategories->list($user, min((int) $request->integer('per_page', 15), 100));

        return SubCategoryResource::collection($subCategories);
    }

    public function store(StoreSubCategoryRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('create', SubCategory::class);

        $subCategory = $this->subCategories->create($user, $request->validated());

        return (new SubCategoryResource($subCategory))->response()->setStatusCode(201);
    }

    public function show(Request $request, SubCategory $subCategory): SubCategoryResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('view', $subCategory);

        return new SubCategoryResource($this->subCategories->show($user, $subCategory->id));
    }

    public function update(UpdateSubCategoryRequest $request, SubCategory $subCategory): SubCategoryResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $subCategory);

        return new SubCategoryResource($this->subCategories->update($user, $subCategory->id, $request->validated()));
    }

    public function destroy(Request $request, SubCategory $subCategory): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('delete', $subCategory);

        $this->subCategories->delete($user, $subCategory->id);

        return response()->json(null, 204);
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
