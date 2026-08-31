<?php

declare(strict_types=1);

namespace Game\Identity\Application;

use DateInterval;
use Game\Identity\Domain\Account;
use Game\Identity\Domain\DeviceSession;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Str;

final readonly class TokenIssuer
{
    public function __construct(private Clock $clock) {}

    /**
     * @param array{device_id?: string, device_name?: string, platform?: string, ip?: string} $device
     * @return array{access_token: string, refresh_token: string}
     */
    public function issue(Account $account, array $device = [], ?string $familyId = null, ?DeviceSession $session = null): array
    {
        $familyId ??= Str::ulid()->toString();
        $refreshSecret = Str::random(64);
        $accessExpiresAt = $this->clock->now()->add(new DateInterval('PT'.max(1, (int) config('game.auth.access_token_ttl_minutes', 60)).'M'));

        $token = $account->createToken('auth', ['*'], $accessExpiresAt);
        $token->accessToken->forceFill([
            'refresh_token' => hash('sha256', $refreshSecret),
            'family_id' => $familyId,
        ])->save();

        $session ??= new DeviceSession;
        $session->forceFill([
            'account_id' => $account->getKey(),
            'token_id' => $token->accessToken->getKey(),
            'device_id' => $device['device_id'] ?? $session->getAttribute('device_id') ?? 'unknown-device',
            'device_name' => $device['device_name'] ?? $session->getAttribute('device_name') ?? 'Unknown device',
            'platform' => $device['platform'] ?? $session->getAttribute('platform') ?? 'unknown',
            'ip' => $device['ip'] ?? $session->getAttribute('ip') ?? '0.0.0.0',
            'last_seen_at' => $this->clock->now(),
            'revoked_at' => null,
        ])->save();

        $maxSessions = max(1, (int) config('game.auth.max_device_sessions', 10));
        $sessionsQuery = DeviceSession::query()
            ->where('account_id', $account->getKey());
        $sessionsQuery->getQuery()->orderByDesc('last_seen_at');
        $sessions = $sessionsQuery->get();

        foreach ($sessions->slice($maxSessions) as $oldSession) {
            $oldSession->delete();
        }

        return [
            'access_token' => $token->plainTextToken,
            'refresh_token' => $familyId.'|'.$refreshSecret,
        ];
    }
}
