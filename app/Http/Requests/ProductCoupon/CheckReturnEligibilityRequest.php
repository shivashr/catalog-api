<?php

namespace App\Http\Requests\ProductCoupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckReturnEligibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'order_reference' => ['required', 'string', 'max:255'],
        ];
    }
}

