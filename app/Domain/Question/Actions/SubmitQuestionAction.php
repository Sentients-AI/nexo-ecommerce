<?php

declare(strict_types=1);

namespace App\Domain\Question\Actions;

use App\Domain\Product\Models\Product;
use App\Domain\Question\Models\Question;
use App\Domain\User\Models\User;

final class SubmitQuestionAction
{
    public function execute(Product $product, User $user, string $body): Question
    {
        return Question::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'body' => $body,
            'is_answered' => false,
        ]);
    }
}
