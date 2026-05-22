<?php

namespace App\Http\Requests\ProductCoupon;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductCouponRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:100'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'min_order' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['required', 'integer', 'min:1'],
            'expiry_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $expiryDate = $this->input('expiry_date');

        if (is_string($expiryDate) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $expiryDate) === 1) {
            $normalized = Carbon::hasFormat($expiryDate, 'd/m/Y')
                ? Carbon::createFromFormat('d/m/Y', $expiryDate)->format('Y-m-d')
                : null;

            if ($normalized !== null) {
                $this->merge(['expiry_date' => $normalized]);
            }
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('discount_type') === 'percentage' && (float) $this->input('value', 0) > 100) {
                    $validator->errors()->add('value', 'Percentage discount value cannot be greater than 100.');
                }
            },
        ];
    }
}
