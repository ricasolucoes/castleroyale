<?php

namespace Game\Identity\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens, HasUlids;

    protected $fillable = [
        'email',
        'password',
        'shield_expires_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'shield_expires_at' => 'datetime',
        ];
    }
}
