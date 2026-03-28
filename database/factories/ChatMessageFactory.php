<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Chat\Models\ChatMessage;
use App\Domain\Chat\Models\Conversation;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
final class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->paragraph(),
            'read_at' => null,
        ];
    }

    public function read(): self
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }
}
