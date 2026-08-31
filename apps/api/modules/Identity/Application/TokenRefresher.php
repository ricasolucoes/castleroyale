<?php

declare(strict_types=1);

namespace Game\Identity\Application;

use DateInterval;
use Game\Identity\Domain\Account;
use Game\Identity\Domain\DeviceSession;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class TokenRefresher
{
    public function __construct(private Clock $clock, private TokenIssuer $tokens) {}

    /**
     * @param array{device_id?: string, device_name?: string, platform?: string, ip?: string} $device
     * @return array{access_token: string, refresh_token: string}
     */
    public function handle(string $refreshToken, array $device = []): array
    {
        $separator = strpos($refreshToken, '|');

        if ($separator === false) {
            throw GameException::of(ErrorCode::TokenExpired, 'Token expired or invalid.');
        }

        $familyId = substr($refreshToken, 0, $separator);
        $refreshSecret = substr($refreshToken, $separator + 1);

        if ($familyId === '' || $refreshSecret === '') {
            throw GameException::of(ErrorCode::TokenExpired, 'Token expired or invalid.');
        }

        $expired = false;
        $result = DB::transaction(function () use ($familyId, $refreshSecret, $device, &$expired): ?array {
            $query = PersonalAccessToken::query()
                ->where('family_id', $familyId)
                ->where('refresh_token', hash('sha256', $refreshSecret));
            $query->getQuery()->lockForUpdate();

            /** @var PersonalAccessToken|null $tokenModel */
            $tokenModel = $query->first();

            if ($tokenModel === null) {
                // The family id is part of the opaque refresh token envelope, so
                // a replay can revoke every still-live token in that family.
                PersonalAccessToken::query()->where('family_id', $familyId)->delete();
                $expired = true;

                return null;
            }

            $createdAt = $tokenModel->created_at;
            if ($createdAt === null || $createdAt->toDateTimeImmutable()->add(
                new DateInterval('P'.max(1, (int) config('game.auth.refresh_token_ttl_days', 30)).'D'),
            ) <= $this->clock->now()) {
                $tokenModel->delete();
                $expired = true;

                return null;
            }

            $account = $tokenModel->tokenable;
            if (! $account instanceof Account) {
                throw GameException::of(ErrorCode::TokenExpired, 'Invalid token.');
            }

            $session = DeviceSession::query()->where('token_id', $tokenModel->getKey())->first();
            if ($session === null) {
                $tokenModel->delete();

                return $this->tokens->issue($account, $device);
            }

            $session->forceFill(['token_id' => null])->save();
            $tokenModel->delete();

            return $this->tokens->issue($account, [
                'device_id' => $device['device_id'] ?? $session->device_id,
                'device_name' => $device['device_name'] ?? $session->device_name,
                'platform' => $device['platform'] ?? $session->platform,
                'ip' => $device['ip'] ?? $session->ip,
            ], $familyId, $session);
        });

        if ($expired || $result === null) {
            throw GameException::of(ErrorCode::TokenExpired, 'Token expired or invalid.');
        }

        return $result;
    }
}
