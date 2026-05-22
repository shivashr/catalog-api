<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\User;
use App\Services\BrandService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brands)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', Brand::class);

        $brands = $this->brands->list($user, min((int) $request->integer('per_page', 15), 100));

        return BrandResource::collection($brands);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('create', Brand::class);

        $brand = $this->brands->create($user, $request->validated());

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function show(Request $request, Brand $brand): BrandResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('view', $brand);

        return new BrandResource($this->brands->show($user, $brand->id));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $brand);

        return new BrandResource($this->brands->update($user, $brand->id, $request->validated()));
    }

    public function destroy(Request $request, Brand $brand): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('delete', $brand);

        $this->brands->delete($user, $brand->id);

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
