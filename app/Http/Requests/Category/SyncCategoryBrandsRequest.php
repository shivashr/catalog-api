<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCategoryBrandsRequest extends FormRequest
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
            'brand_ids' => ['required', 'array'],
            'brand_ids.*' => ['integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
