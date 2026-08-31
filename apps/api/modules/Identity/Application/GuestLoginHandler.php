<?php

declare(strict_types=1);

namespace Game\Identity\Application;

use DateInterval;
use Game\Identity\Domain\Account;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class GuestLoginHandler
{
    public function __construct(private Clock $clock, private TokenIssuer $tokens) {}

    /**
     * @param array{device_id?: string, device_name?: string, platform?: string, ip?: string} $device
     * @return array{access_token: string, refresh_token: string}
     */
    public function handle(array $device = []): array
    {
        return DB::transaction(function () use ($device): array {
            $account = Account::forceCreate([
                'id' => Str::ulid()->toString(),
                'email' => null,
                'password' => null,
                'is_guest' => true,
                'shield_expires_at' => $this->clock->now()->add(new DateInterval('P7D')),
            ]);

            return $this->tokens->issue($account, $device);
        });
    }
}
