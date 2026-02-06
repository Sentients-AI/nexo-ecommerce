<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ApplyPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Promotion code is required.',
            'code.max' => 'Promotion code must not exceed 50 characters.',
            'cart_id.required' => 'Cart ID is required.',
            'cart_id.exists' => 'Cart not found.',
        ];
    }
}
