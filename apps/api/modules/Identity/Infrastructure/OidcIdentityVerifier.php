<?php

declare(strict_types=1);

namespace Game\Identity\Infrastructure;

use Game\Identity\Domain\SocialIdentityVerifier;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class OidcIdentityVerifier implements SocialIdentityVerifier
{
    /**
     * @return array{provider_id: string, email: string|null}
     */
    public function verify(string $provider, string $identityToken): array
    {
        $settings = config('services.'.$provider);
        if (! is_array($settings)) {
            throw $this->invalidToken();
        }

        $parts = explode('.', $identityToken);
        if (count($parts) !== 3) {
            throw $this->invalidToken();
        }

        $header = $this->decodeJson($parts[0]);
        $claims = $this->decodeJson($parts[1]);
        $algorithm = $header['alg'] ?? null;
        $keyId = $header['kid'] ?? null;

        if ($algorithm !== 'RS256' || ! is_string($keyId)) {
            throw $this->invalidToken();
        }

        $now = time();
        $expiresAt = $claims['exp'] ?? null;
        $issuer = $claims['iss'] ?? null;
        $audience = $claims['aud'] ?? null;
        $clientIds = $settings['client_ids'] ?? [];
        $issuers = $settings['issuers'] ?? [];

        if (! is_int($expiresAt) && ! is_float($expiresAt)) {
            throw $this->invalidToken();
        }

        $audiences = is_array($audience) ? $audience : [$audience];
        if ($expiresAt <= $now || ! is_string($issuer) || ! in_array($issuer, $issuers, true)
            || array_intersect($audiences, $clientIds) === []) {
            throw $this->invalidToken();
        }

        $key = collect($this->keys($provider, $settings['jwks_url'] ?? null))
            ->first(fn (mixed $candidate): bool => is_array($candidate) && ($candidate['kid'] ?? null) === $keyId);
        if (! is_array($key)) {
            throw $this->invalidToken();
        }

        $signature = base64_decode($this->base64Url($parts[2]), true);
        if (! is_string($signature) || openssl_verify($parts[0].'.'.$parts[1], $signature, $this->publicKey($key), OPENSSL_ALGO_SHA256) !== 1) {
            throw $this->invalidToken();
        }

        $subject = $claims['sub'] ?? null;
        if (! is_string($subject) || $subject === '') {
            throw $this->invalidToken();
        }

        return [
            'provider_id' => $subject,
            'email' => is_string($claims['email'] ?? null) ? $claims['email'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function keys(string $provider, mixed $url): array
    {
        if (! is_string($url) || $url === '') {
            throw $this->invalidToken();
        }

        $keys = Cache::remember('identity-jwks-'.$provider, 3600, static function () use ($url): array {
            $response = Http::acceptJson()->timeout(5)->get($url);
            if (! $response->successful()) {
                return [];
            }

            $value = $response->json('keys');

            return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
        });

        return is_array($keys) ? array_values(array_filter($keys, 'is_array')) : [];
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $value): array
    {
        $decoded = json_decode($this->base64Url($value), true);

        if (! is_array($decoded)) {
            throw $this->invalidToken();
        }

        return $decoded;
    }

    private function base64Url(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').'='.str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        if (! is_string($decoded)) {
            throw $this->invalidToken();
        }

        return $decoded;
    }

    /** @param array<string, mixed> $key */
    private function publicKey(array $key): string
    {
        $modulus = $key['n'] ?? null;
        $exponent = $key['e'] ?? null;
        if (! is_string($modulus) || ! is_string($exponent)) {
            throw $this->invalidToken();
        }

        $modulus = $this->base64Url($modulus);
        $exponent = $this->base64Url($exponent);
        $rsa = $this->asn1Sequence($this->asn1Integer($modulus).$this->asn1Integer($exponent));
        $der = $this->asn1Sequence($this->asn1Sequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00").$this->asn1BitString($rsa));

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $value): string
    {
        if (ord($value[0]) > 127) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1BitString(string $value): string
    {
        return "\x03".$this->asn1Length(strlen($value) + 1)."\x00".$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = ltrim(pack('N', $length), "\x00");

        return chr(128 + strlen($encoded)).$encoded;
    }

    private function invalidToken(): GameException
    {
        return GameException::of(ErrorCode::InvalidCredentials, 'The social identity token is invalid.');
    }
}
