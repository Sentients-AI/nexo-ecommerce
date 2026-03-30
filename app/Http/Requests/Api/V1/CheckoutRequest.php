<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class CheckoutRequest extends FormRequest
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
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', config('currency.supported', []))],
            'promotion_code' => ['nullable', 'string', 'max:50'],
            'redeem_points' => ['nullable', 'integer', 'min:1'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'guest_email' => $this->user() ? ['nullable'] : ['required', 'email', 'max:255'],
            'guest_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cart_id.required' => 'Cart ID is required.',
            'cart_id.exists' => 'Cart not found.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be a 3-letter code.',
        ];
    }
}
