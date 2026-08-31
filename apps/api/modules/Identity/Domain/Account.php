<?php

declare(strict_types=1);

namespace Game\Identity\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $shield_expires_at
 * @property bool $is_guest
 * @property string|null $provider
 * @property string|null $provider_id
 */
class Account extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\Game\Identity\Domain\AccountFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'email',
        'password',
        'shield_expires_at',
        'is_guest',
        'provider',
        'provider_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'shield_expires_at' => 'datetime',
            'is_guest' => 'boolean',
        ];
    }
}
