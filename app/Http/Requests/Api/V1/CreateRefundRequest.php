<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class CreateRefundRequest extends FormRequest
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
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_cents.required' => 'Refund amount is required.',
            'amount_cents.min' => 'Refund amount must be at least 1 cent.',
            'reason.required' => 'Refund reason is required.',
            'reason.max' => 'Refund reason cannot exceed 500 characters.',
        ];
    }
}
