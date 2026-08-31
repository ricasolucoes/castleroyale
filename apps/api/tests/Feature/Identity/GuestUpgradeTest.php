<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Game\Identity\Domain\Account;
use Game\Identity\Domain\SocialIdentityVerifier;
use Game\Shared\Application\Error\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GuestUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_upgrade_preserves_the_account_id_and_progress_owner(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest', [], ['Idempotency-Key' => 'guest-1'])->json('data');
        $account = Account::query()->firstOrFail();
        $accountId = $account->getKey();

        $response = $this->withHeader('Authorization', 'Bearer '.$guest['access_token'])
            ->postJson('/api/v1/auth/upgrade', [
                'email' => 'upgraded@example.test',
                'password' => 'strong-password',
            ], ['Idempotency-Key' => 'upgrade-1']);
        expect($response)->toBeApiSuccess();

        $account->refresh();
        $this->assertSame($accountId, $account->getKey());
        $this->assertFalse($account->is_guest);
        $this->assertSame('upgraded@example.test', $account->email);
        $this->assertTrue(password_verify('strong-password', (string) $account->password));
    }

    public function test_social_upgrade_is_disabled_without_provider_configuration(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest', [], ['Idempotency-Key' => 'guest-1'])->json('data');

        $response = $this->withHeader('Authorization', 'Bearer '.$guest['access_token'])
            ->postJson('/api/v1/auth/upgrade', [
                'provider' => 'google',
                'identity_token' => 'not-used-when-disabled',
            ], ['Idempotency-Key' => 'upgrade-1']);
        expect($response)->toBeApiError(ErrorCode::FeatureDisabled);
    }

    public function test_social_login_persists_verified_subject_instead_of_the_raw_token(): void
    {
        config(['services.google.client_ids' => ['google-client']]);
        app()->instance(SocialIdentityVerifier::class, new class implements SocialIdentityVerifier
        {
            public function verify(string $provider, string $identityToken): array
            {
                return ['provider_id' => 'verified-subject', 'email' => 'social@example.test'];
            }
        });

        $response = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'identity_token' => 'raw-provider-token',
        ], ['Idempotency-Key' => 'social-1']);

        expect($response)->toBeApiSuccess();
        $this->assertDatabaseHas('accounts', [
            'provider' => 'google',
            'provider_id' => 'verified-subject',
        ]);
        $this->assertDatabaseMissing('accounts', ['provider_id' => 'raw-provider-token']);
    }
}
