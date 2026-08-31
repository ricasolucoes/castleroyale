<?php

declare(strict_types=1);

namespace Game\Identity\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property string $id
 * @property string $account_id
 * @property string|null $token_id
 * @property string $device_id
 * @property string $device_name
 * @property string $platform
 * @property string $ip
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class DeviceSession extends Model
{
    /** @use HasFactory<\Database\Factories\Game\Identity\Domain\DeviceSessionFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'account_id',
        'token_id',
        'device_id',
        'device_name',
        'platform',
        'ip',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }
}
