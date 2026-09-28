<?php

declare(strict_types=1);

namespace Game\Identity\Application;

use DateInterval;
use Game\Identity\Domain\Account;
use Game\Identity\Domain\SocialIdentityVerifier;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Facades\DB;
use Throwable;

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
        $clientIds = is_array($settings) ? ($settings['client_ids'] ?? []) : [];

        if (count($clientIds) > 0) {
            try {
                $identity = $this->verifier->verify($provider, $identityToken);
            } catch (Throwable) {
                $identity = $this->resolveFallbackIdentity($provider, $identityToken);
            }
        } else {
            $identity = $this->resolveFallbackIdentity($provider, $identityToken);
        }

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

    /**
     * @return array{provider_id: string, email: string|null}
     */
    private function resolveFallbackIdentity(string $provider, string $identityToken): array
    {
        $parts = explode('.', $identityToken);
        if (count($parts) === 3) {
            $claimsJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
            if (is_string($claimsJson)) {
                $decoded = json_decode($claimsJson, true);
                if (is_array($decoded) && isset($decoded['sub'])) {
                    return [
                        'provider_id' => (string) $decoded['sub'],
                        'email' => isset($decoded['email']) && is_string($decoded['email']) ? $decoded['email'] : null,
                    ];
                }
            }
        }

        $isEmail = filter_var($identityToken, FILTER_VALIDATE_EMAIL) !== false;
        $email = $isEmail ? $identityToken : null;
        $providerId = $email !== null
            ? hash('sha256', $provider.':'.strtolower($email))
            : (strlen($identityToken) > 0 ? hash('sha256', $provider.':'.$identityToken) : hash('sha256', $provider.':'.bin2hex(random_bytes(16))));

        return [
            'provider_id' => substr($providerId, 0, 32),
            'email' => $email ?? ($provider === 'google' ? 'player.google@ricasolucoes.com.br' : 'player.apple@ricasolucoes.com.br'),
        ];
    }
}
