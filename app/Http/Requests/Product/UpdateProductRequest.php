<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\DB;

class UpdateProductRequest extends FormRequest
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
        $product = $this->route('product');
        $productId = is_object($product) ? $product->getKey() : $product;

        return [
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'sub_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sub_categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'condition' => ['sometimes', 'nullable', Rule::in(['new', 'used', 'refurbished'])],
            'description' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_array($value) && ! is_string($value)) {
                    $fail('The tags field must be an array or comma-separated string.');
                }
            }],
            'tags.*' => ['string', 'max:100'],
            'selling_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'mrp_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'low_stock_alert' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->where('tenant_id', $tenantId)->ignore($productId),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'shipping_class' => ['sometimes', 'nullable', 'string', 'max:255'],
            'free_shipping' => ['sometimes', 'nullable', 'boolean'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'draft', 'inactive'])],
            'images' => ['sometimes', 'nullable', 'array', 'max:12'],
            'images.*' => ['file', 'image', 'max:5120'],
            'variants' => ['sometimes', 'nullable', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_array($value)) {
                    $fail('The variants field must be an array.');

                    return;
                }

                $allowedTypes = ['size', 'color', 'material'];
                $isListPayload = array_is_list($value);

                if ($isListPayload) {
                    foreach ($value as $variant) {
                        if (! is_array($variant)) {
                            $fail('Each variant must be an object with type and value.');

                            return;
                        }

                        $type = $variant['type'] ?? null;
                        $variantValue = $variant['value'] ?? null;

                        if (! in_array($type, $allowedTypes, true) || ! is_string($variantValue) || $variantValue === '') {
                            $fail('Each variant must contain valid type and value fields.');

                            return;
                        }
                    }

                    return;
                }

                foreach ($value as $type => $variantValues) {
                    if (! in_array((string) $type, $allowedTypes, true) || ! is_array($variantValues)) {
                        $fail('Variants object keys must be size, color, or material arrays.');

                        return;
                    }

                    foreach ($variantValues as $variantValue) {
                        if (! is_string($variantValue) || trim($variantValue) === '') {
                            $fail('Grouped variant values must be non-empty strings.');

                            return;
                        }
                    }
                }
            }],
            'specifications' => ['sometimes', 'nullable', 'array'],
            'specifications.*.attribute' => ['required_with:specifications', 'string', 'max:255'],
            'specifications.*.value' => ['required_with:specifications', 'string', 'max:255'],
            'specifications.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'warranty' => ['sometimes', 'nullable', 'array'],
            'warranty.warranty_duration' => ['nullable', 'integer', 'min:0'],
            'warranty.warranty_unit' => ['nullable', Rule::in(['days', 'months', 'years'])],
            'warranty.warranty_by' => ['nullable', Rule::in(['seller', 'manufacturer', 'no_warranty'])],
            'warranty.return_window' => ['nullable', 'string', 'max:255'],
            'warranty.return_type' => ['nullable', Rule::in(['return_refund', 'exchange_only', 'no_return'])],
            'seo' => ['sometimes', 'nullable', 'array'],
            'seo.meta_title' => ['nullable', 'string', 'max:255'],
            'seo.meta_description' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');

                if (! $product instanceof Product) {
                    return;
                }

                $targetStatus = $this->input('status', $product->status);

                $tenantId = $this->user()?->tenant_id;
                $categoryId = $this->has('category_id') ? $this->input('category_id') : $product->category_id;
                $brandId = $this->has('brand_id') ? $this->input('brand_id') : $product->brand_id;
                $subCategoryId = $this->has('sub_category_id') ? $this->input('sub_category_id') : $product->sub_category_id;

                if (($brandId !== null || $subCategoryId !== null) && blank($categoryId)) {
                    $validator->errors()->add('category_id', 'A category is required when selecting brand or sub-category.');

                    return;
                }

                if ($tenantId !== null && $categoryId !== null && $brandId !== null) {
                    $brandLinkedToCategory = DB::table('category_brand')
                        ->where('tenant_id', $tenantId)
                        ->where('category_id', (int) $categoryId)
                        ->where('brand_id', (int) $brandId)
                        ->exists();

                    if (! $brandLinkedToCategory) {
                        $validator->errors()->add('brand_id', 'The selected brand is not linked to the selected category.');
                    }
                }

                if ($tenantId !== null && $categoryId !== null && $subCategoryId !== null) {
                    $subCategoryMatchesCategory = DB::table('sub_categories')
                        ->where('tenant_id', $tenantId)
                        ->where('id', (int) $subCategoryId)
                        ->where('category_id', (int) $categoryId)
                        ->exists();

                    if (! $subCategoryMatchesCategory) {
                        $validator->errors()->add('sub_category_id', 'The selected sub-category does not belong to the selected category.');
                    }
                }

                if ($targetStatus !== 'active') {
                    return;
                }

                $uploadedImageCount = count($this->file('images', []));
                $existingImageCount = $product->images()->count();

                if ($uploadedImageCount + $existingImageCount < 1) {
                    $validator->errors()->add('images', 'At least one product image is required to publish an active product.');
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
                    $value = $this->has($field) ? $this->input($field) : $product->{$field};

                    if ($value === null || $value === '') {
                        $validator->errors()->add($field, $message);
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'A category is required to publish an active product.',
            'brand_id.required' => 'A brand is required to publish an active product.',
            'sub_category_id.required' => 'A sub-category is required to publish an active product.',
            'name.required' => 'A product name is required to publish an active product.',
            'condition.required' => 'A condition is required to publish an active product.',
            'description.required' => 'A description is required to publish an active product.',
            'selling_price.required' => 'A selling price is required to publish an active product.',
            'stock_quantity.required' => 'Stock quantity is required to publish an active product.',
            'sku.required' => 'An SKU is required to publish an active product.',
        ];
    }
}
