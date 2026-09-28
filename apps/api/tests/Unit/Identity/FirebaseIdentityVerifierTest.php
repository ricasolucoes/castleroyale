<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Game\Identity\Infrastructure\OidcIdentityVerifier;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FirebaseIdentityVerifierTest extends TestCase
{
    private string $privateKeyPem;

    private string $modulusB64Url;

    private string $exponentB64Url;

    protected function setUp(): void
    {
        parent::setUp();

        $res = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($res);
        $pem = '';
        openssl_pkey_export($res, $pem);
        $this->privateKeyPem = $pem;
        $details = openssl_pkey_get_details($res);
        $this->assertIsArray($details);

        $this->modulusB64Url = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $this->exponentB64Url = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');
    }

    public function test_verifies_valid_firebase_google_token_successfully(): void
    {
        config([
            'services.firebase.project_id' => 'castle-royale-prod',
            'services.google.client_ids' => ['castle-royale-prod'],
            'services.google.issuers' => ['https://securetoken.google.com/castle-royale-prod'],
            'services.google.firebase_jwks_url' => 'https://mock.firebase.jwks/certs',
        ]);

        Http::fake([
            'https://mock.firebase.jwks/certs' => Http::response([
                'keys' => [
                    [
                        'kid' => 'kid-1',
                        'kty' => 'RSA',
                        'alg' => 'RS256',
                        'n' => $this->modulusB64Url,
                        'e' => $this->exponentB64Url,
                    ],
                ],
            ]),
        ]);

        $token = $this->createJwt(
            header: ['alg' => 'RS256', 'kid' => 'kid-1'],
            claims: [
                'iss' => 'https://securetoken.google.com/castle-royale-prod',
                'aud' => 'castle-royale-prod',
                'sub' => 'firebase-uid-999',
                'email' => 'sovereign@ricasolucoes.com.br',
                'exp' => time() + 3600,
                'firebase' => [
                    'sign_in_provider' => 'google.com',
                ],
            ],
        );

        $verifier = new OidcIdentityVerifier;
        $identity = $verifier->verify('google', $token);

        $this->assertSame('firebase-uid-999', $identity['provider_id']);
        $this->assertSame('sovereign@ricasolucoes.com.br', $identity['email']);
    }

    public function test_rejects_expired_firebase_token(): void
    {
        config([
            'services.google.client_ids' => ['castle-royale-prod'],
            'services.google.issuers' => ['https://securetoken.google.com/castle-royale-prod'],
            'services.google.firebase_jwks_url' => 'https://mock.firebase.jwks/certs',
        ]);

        $token = $this->createJwt(
            header: ['alg' => 'RS256', 'kid' => 'kid-1'],
            claims: [
                'iss' => 'https://securetoken.google.com/castle-royale-prod',
                'aud' => 'castle-royale-prod',
                'sub' => 'firebase-uid-999',
                'exp' => time() - 60,
                'firebase' => ['sign_in_provider' => 'google.com'],
            ],
        );

        $verifier = new OidcIdentityVerifier;

        $this->expectException(GameException::class);
        try {
            $verifier->verify('google', $token);
        } catch (GameException $e) {
            $this->assertSame(ErrorCode::InvalidCredentials, $e->errorCode);
            throw $e;
        }
    }

    public function test_rejects_firebase_token_with_wrong_audience(): void
    {
        config([
            'services.google.client_ids' => ['castle-royale-prod'],
            'services.google.issuers' => ['https://securetoken.google.com/castle-royale-prod'],
            'services.google.firebase_jwks_url' => 'https://mock.firebase.jwks/certs',
        ]);

        $token = $this->createJwt(
            header: ['alg' => 'RS256', 'kid' => 'kid-1'],
            claims: [
                'iss' => 'https://securetoken.google.com/castle-royale-prod',
                'aud' => 'other-project-id',
                'sub' => 'firebase-uid-999',
                'exp' => time() + 3600,
                'firebase' => ['sign_in_provider' => 'google.com'],
            ],
        );

        $verifier = new OidcIdentityVerifier;

        $this->expectException(GameException::class);
        $verifier->verify('google', $token);
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $claims
     */
    private function createJwt(array $header, array $claims): string
    {
        $h = rtrim(strtr(base64_encode((string) json_encode($header)), '+/', '-_'), '=');
        $c = rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');
        $payload = $h.'.'.$c;

        $signature = '';
        openssl_sign($payload, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
        $s = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $payload.'.'.$s;
    }
}
