<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductImagesRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UpdateProductStatusRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', Product::class);

        $products = $this->products->list(
            $user,
            min((int) $request->integer('per_page', 15), 100)
        );

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('create', Product::class);

        $product = $this->products->create($user, $request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('view', $product);

        return new ProductResource($this->products->show($user, $product->id));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $product);

        return new ProductResource($this->products->update($user, $product->id, $request->validated()));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('delete', $product);

        $this->products->delete($user, $product->id);

        return response()->json(null, 204);
    }

    public function storeImages(StoreProductImagesRequest $request, Product $product): ProductResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $product);

        return new ProductResource(
            $this->products->addImages($user, $product->id, $request->validated('images'))
        );
    }

    public function destroyImage(Request $request, Product $product, int $image): ProductResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $product);

        return new ProductResource($this->products->deleteImage($user, $product->id, $image));
    }

    public function updateStatus(UpdateProductStatusRequest $request, Product $product): ProductResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $product);

        return new ProductResource(
            $this->products->updateStatus($user, $product->id, $request->validated('status'))
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
