<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Placeholder account model.
 *
 * The real identity model — device sessions, guest accounts, social providers,
 * token rotation — lands in GSD Phase 03. Until then this exists only so the
 * back office and Horizon have something to authenticate and authorise
 * against. Do not build gameplay on it.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_staff
 * @property string $password
 */
final class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Only staff accounts reach the back office, and never by guessing the URL.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_staff === true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'boolean',
        ];
    }
}
