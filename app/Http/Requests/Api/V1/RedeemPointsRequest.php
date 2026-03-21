<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class RedeemPointsRequest extends FormRequest
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
        $minimum = config('loyalty.minimum_redemption', 100);

        return [
            'points' => ['required', 'integer', "min:{$minimum}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $minimum = config('loyalty.minimum_redemption', 100);

        return [
            'points.required' => 'Points amount is required.',
            'points.integer' => 'Points must be a whole number.',
            'points.min' => "Minimum redemption is {$minimum} points.",
        ];
    }
}
