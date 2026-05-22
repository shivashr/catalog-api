<?php

namespace App\Http\Requests\SubCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubCategoryRequest extends FormRequest
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
        $subCategory = $this->route('sub_category');
        $subCategoryId = is_object($subCategory) ? $subCategory->getKey() : $subCategory;

        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('sub_categories', 'slug')->where('tenant_id', $tenantId)->ignore($subCategoryId)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
