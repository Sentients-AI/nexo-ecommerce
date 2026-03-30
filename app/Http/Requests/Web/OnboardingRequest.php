<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class OnboardingRequest extends FormRequest
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
        $reserved = config('tenancy.reserved_subdomains', []);

        return [
            'store_name' => ['required', 'string', 'max:100'],
            'store_slug' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/',
                'not_in:'.implode(',', $reserved),
                'unique:tenants,slug',
            ],
            'store_email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_slug.regex' => 'The store URL can only contain lowercase letters, numbers, and hyphens, and must start and end with a letter or number.',
            'store_slug.not_in' => 'That subdomain is reserved. Please choose a different one.',
            'store_slug.unique' => 'That store URL is already taken. Please choose a different one.',
            'email.unique' => 'An account with that email already exists.',
        ];
    }
}
