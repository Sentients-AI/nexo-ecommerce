<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreConversationRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:store,support'],
            'subject' => ['nullable', 'string', 'max:255'],
            'initial_message' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'A conversation type is required.',
            'type.in' => 'Conversation type must be store or support.',
            'initial_message.required' => 'An initial message is required.',
            'initial_message.max' => 'Message cannot exceed 5000 characters.',
            'subject.max' => 'Subject cannot exceed 255 characters.',
        ];
    }
}
