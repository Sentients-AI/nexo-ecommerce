<?php

declare(strict_types=1);

namespace App\Domain\Question\Actions;

use App\Domain\Question\Models\Question;
use App\Domain\Question\Models\QuestionAnswer;
use App\Domain\User\Models\User;

final class SubmitAnswerAction
{
    public function execute(Question $question, User $user, string $body, bool $isVendorAnswer): QuestionAnswer
    {
        $answer = $question->answers()->create([
            'user_id' => $user->id,
            'body' => $body,
            'is_vendor_answer' => $isVendorAnswer,
        ]);

        if (! $question->is_answered) {
            $question->update(['is_answered' => true]);
        }

        return $answer->load('user');
    }
}
