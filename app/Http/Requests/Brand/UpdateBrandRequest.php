<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
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
        $brand = $this->route('brand');
        $brandId = is_object($brand) ? $brand->getKey() : $brand;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('brands', 'slug')->where('tenant_id', $tenantId)->ignore($brandId)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
