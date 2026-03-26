<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ReviewPhotoFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class ReviewPhoto extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['tenant_id', 'review_id', 'path', 'disk', 'order'];

    /**
     * Get the review that this photo belongs to.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * Get the public URL for this photo.
     */
    public function url(): Attribute
    {
        return Attribute::make(get: fn () => Storage::disk($this->disk)->url($this->path));
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReviewPhotoFactory
    {
        return ReviewPhotoFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['order' => 'integer'];
    }
}
