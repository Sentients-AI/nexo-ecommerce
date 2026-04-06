<?php

declare(strict_types=1);

namespace App\Domain\Question\Models;

use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuestionAnswer extends BaseModel
{
    protected $fillable = [
        'question_id',
        'user_id',
        'body',
        'is_vendor_answer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    protected function casts(): array
    {
        return [
            'is_vendor_answer' => 'boolean',
        ];
    }
}
