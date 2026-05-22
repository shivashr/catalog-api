<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ProductCouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isExpired = Carbon::parse($this->expiry_date)->isBefore(Carbon::today());

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'value' => $this->value,
            'min_order' => $this->min_order,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'status' => $this->status,
            'is_expired' => $isExpired,
            'tab_status' => $isExpired ? 'expired' : $this->status,
            'exchange_only_on_return' => $this->exchange_only_on_return,
            'product' => $this->whenLoaded('product', fn (): ?array => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'status' => $this->product->status,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

