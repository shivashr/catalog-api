<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\DB;

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
                    'brand_id' => 'A brand is required to publish an active product.',
                    'sub_category_id' => 'A sub-category is required to publish an active product.',
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

                if ($this->user()?->tenant_id !== null && $product->category_id !== null && $product->brand_id !== null) {
                    $brandLinkedToCategory = DB::table('category_brand')
                        ->where('tenant_id', $this->user()?->tenant_id)
                        ->where('category_id', (int) $product->category_id)
                        ->where('brand_id', (int) $product->brand_id)
                        ->exists();

                    if (! $brandLinkedToCategory) {
                        $validator->errors()->add('brand_id', 'The selected brand is not linked to the selected category.');
                    }
                }

                if ($this->user()?->tenant_id !== null && $product->category_id !== null && $product->sub_category_id !== null) {
                    $subCategoryMatchesCategory = DB::table('sub_categories')
                        ->where('tenant_id', $this->user()?->tenant_id)
                        ->where('id', (int) $product->sub_category_id)
                        ->where('category_id', (int) $product->category_id)
                        ->exists();

                    if (! $subCategoryMatchesCategory) {
                        $validator->errors()->add('sub_category_id', 'The selected sub-category does not belong to the selected category.');
                    }
                }
            },
        ];
    }
}
