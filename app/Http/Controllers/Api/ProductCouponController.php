<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCoupon\CheckReturnEligibilityRequest;
use App\Http\Requests\ProductCoupon\RedeemProductCouponRequest;
use App\Http\Requests\ProductCoupon\StoreProductCouponRequest;
use App\Http\Requests\ProductCoupon\UpdateProductCouponRequest;
use App\Http\Resources\ProductCouponResource;
use App\Models\ProductCoupon;
use App\Models\User;
use App\Services\ProductCouponService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductCouponController extends Controller
{
    public function __construct(private readonly ProductCouponService $coupons)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', ProductCoupon::class);

        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'active', 'disabled', 'expired'], true)) {
            $status = 'all';
        }

        $coupons = $this->coupons->list(
            $user,
            $status,
            min((int) $request->integer('per_page', 15), 100)
        );

        return ProductCouponResource::collection($coupons);
    }

    public function store(StoreProductCouponRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('create', ProductCoupon::class);

        $coupon = $this->coupons->create($user, $request->validated());

        return (new ProductCouponResource($coupon))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ProductCoupon $productCoupon): ProductCouponResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('view', $productCoupon);

        return new ProductCouponResource(
            $this->coupons->show($user, $productCoupon->id)
        );
    }

    public function update(UpdateProductCouponRequest $request, ProductCoupon $productCoupon): ProductCouponResource
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $productCoupon);

        return new ProductCouponResource(
            $this->coupons->update($user, $productCoupon->id, $request->validated())
        );
    }

    public function destroy(Request $request, ProductCoupon $productCoupon): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('delete', $productCoupon);

        $this->coupons->delete($user, $productCoupon->id);

        return response()->json(null, 204);
    }

    public function redeem(RedeemProductCouponRequest $request, ProductCoupon $productCoupon): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('update', $productCoupon);

        $result = $this->coupons->redeem($user, $productCoupon->id, $request->validated());

        return response()->json([
            'data' => [
                'coupon' => (new ProductCouponResource($result['coupon']))->resolve(),
                'usage_id' => $result['usage_id'],
                'return_policy' => $result['return_policy'],
            ],
        ]);
    }

    public function checkReturnEligibility(CheckReturnEligibilityRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('viewAny', ProductCoupon::class);

        $result = $this->coupons->checkReturnEligibility($user, $request->validated());

        return response()->json(['data' => $result]);
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
