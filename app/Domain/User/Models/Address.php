<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Address extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $table = 'user_addresses';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
