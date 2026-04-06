<?php

declare(strict_types=1);

namespace App\Domain\Question\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Question extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'user_id',
        'body',
        'is_answered',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class)->orderBy('is_vendor_answer', 'desc')->orderBy('created_at');
    }

    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->where('is_answered', false);
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_answered' => 'boolean',
        ];
    }
}
