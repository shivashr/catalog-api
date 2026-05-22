<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateProductStatusRequest extends FormRequest
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
        return [
            'status' => ['required', Rule::in(['active', 'draft', 'inactive'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');

                if ($this->input('status') !== 'active' || ! $product instanceof Product) {
                    return;
                }

                $requiredFields = [
                    'name' => 'A product name is required to publish an active product.',
                    'category_id' => 'A category is required to publish an active product.',
                    'condition' => 'A condition is required to publish an active product.',
                    'description' => 'A description is required to publish an active product.',
                    'selling_price' => 'A selling price is required to publish an active product.',
                    'stock_quantity' => 'Stock quantity is required to publish an active product.',
                    'sku' => 'An SKU is required to publish an active product.',
                ];

                foreach ($requiredFields as $field => $message) {
                    if (blank($product->{$field})) {
                        $validator->errors()->add($field, $message);
                    }
                }

                if ($product->images()->count() < 1) {
                    $validator->errors()->add('images', 'At least one product image is required to publish an active product.');
                }
            },
        ];
    }
}
