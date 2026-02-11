<?php

declare(strict_types=1);

namespace App\Domain\FeatureFlag\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;

final class FeatureFlag extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_enabled',
        'conditions',
        'enabled_by',
        'enabled_at',
        'disabled_by',
        'disabled_at',
    ];

    public static function isEnabled(string $key): bool
    {
        $tenantId = Context::get('tenant_id');
        $cacheKey = $tenantId ? "feature_flag:{$tenantId}:{$key}" : "feature_flag:{$key}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            fn () => self::query()->where('key', $key)->value('is_enabled') ?? false
        );
    }

    public static function clearCache(string $key): void
    {
        $tenantId = Context::get('tenant_id');
        $cacheKey = $tenantId ? "feature_flag:{$tenantId}:{$key}" : "feature_flag:{$key}";
        Cache::forget($cacheKey);
    }

    public static function clearAllCache(): void
    {
        $keys = self::query()->pluck('key');
        foreach ($keys as $key) {
            self::clearCache($key);
        }
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    public function enable(int $userId): void
    {
        $this->update([
            'is_enabled' => true,
            'enabled_by' => $userId,
            'enabled_at' => now(),
        ]);
        self::clearCache($this->key);
    }

    public function disable(int $userId): void
    {
        $this->update([
            'is_enabled' => false,
            'disabled_by' => $userId,
            'disabled_at' => now(),
        ]);
        self::clearCache($this->key);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'conditions' => 'array',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }
}
