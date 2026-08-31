<?php

declare(strict_types=1);

namespace Game\Identity\Application;

use DateInterval;
use Game\Identity\Domain\Account;
use Game\Identity\Domain\SocialIdentityVerifier;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Facades\DB;

final readonly class SocialLoginHandler
{
    public function __construct(private Clock $clock, private SocialIdentityVerifier $verifier, private TokenIssuer $tokens) {}

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    /**
     * @param array{device_id?: string, device_name?: string, platform?: string, ip?: string} $device
     * @return array{access_token: string, refresh_token: string}
     */
    public function handle(string $provider, string $identityToken, array $device = []): array
    {
        $settings = config('services.'.$provider);
        if (! is_array($settings) || ($settings['client_ids'] ?? []) === []) {
            throw GameException::of(ErrorCode::FeatureDisabled, 'Provider not configured.');
        }

        $identity = $this->verifier->verify($provider, $identityToken);

        return DB::transaction(function () use ($provider, $identity, $device): array {
            $account = Account::updateOrCreate(
                ['provider' => $provider, 'provider_id' => $identity['provider_id']],
                [
                    'email' => $identity['email'],
                    'shield_expires_at' => $this->clock->now()->add(new DateInterval('P7D')),
                    'is_guest' => false,
                ],
            );

            return $this->tokens->issue($account, $device);
        });
    }
}
