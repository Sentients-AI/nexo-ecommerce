<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Models;

use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Tenant extends BaseModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'email',
        'is_active',
        'settings',
        'trial_ends_at',
        'subscribed_at',
    ];

    /**
     * Determine if the tenant is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Determine if the tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        if ($this->trial_ends_at === null) {
            return false;
        }

        return $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the tenant has an active subscription.
     */
    public function isSubscribed(): bool
    {
        return $this->subscribed_at !== null;
    }

    /**
     * Get a setting value by key.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Get the users belonging to this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScopes();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'subscribed_at' => 'datetime',
        ];
    }
}
