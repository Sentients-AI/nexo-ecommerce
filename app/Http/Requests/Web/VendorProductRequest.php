<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\Rule;

final class VendorProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->where('tenant_id', Context::get('tenant_id'))->ignore($productId)],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->where('tenant_id', Context::get('tenant_id'))->ignore($productId)],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'price_cents' => ['required', 'numeric', 'min:0.01'],
            'sale_price' => ['nullable', 'numeric', 'min:0.01', 'lt:price_cents'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['url'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'sku.unique' => 'A product with that SKU already exists.',
            'slug.unique' => 'A product with that slug already exists.',
            'category_id.exists' => 'The selected category does not exist.',
            'sale_price.lt' => 'The sale price must be less than the regular price.',
        ];
    }
}
