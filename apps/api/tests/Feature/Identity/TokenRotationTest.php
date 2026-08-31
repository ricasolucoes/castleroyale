<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class TokenRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_pair(): void
    {
        $account = Account::factory()->create(['email' => 'player@example.test', 'password' => 'password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $account->email,
            'password' => 'password',
        ], ['Idempotency-Key' => 'login-1']);

        expect($response)->toBeApiSuccess();
        $response->assertJsonPath('data.refresh_token', fn (mixed $value): bool => is_string($value));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('device_sessions', 1);
    }

    public function test_refresh_rotates_the_secret_inside_the_same_family(): void
    {
        $account = Account::factory()->create(['email' => 'player@example.test', 'password' => 'password']);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $account->email,
            'password' => 'password',
        ], ['Idempotency-Key' => 'login-1'])->json('data');
        $familyId = PersonalAccessToken::query()->value('family_id');

        $refresh = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ], ['Idempotency-Key' => 'refresh-1']);

        expect($refresh)->toBeApiSuccess();
        $refresh->assertJsonPath('data.refresh_token', fn (mixed $value): bool => $value !== $login['refresh_token']);
        $this->assertSame($familyId, PersonalAccessToken::query()->value('family_id'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('device_sessions', 1);
    }

    public function test_reusing_a_rotated_refresh_token_revokes_the_whole_family(): void
    {
        $account = Account::factory()->create(['email' => 'player@example.test', 'password' => 'password']);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $account->email,
            'password' => 'password',
        ], ['Idempotency-Key' => 'login-1'])->json('data');

        $rotated = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ], ['Idempotency-Key' => 'refresh-1']);
        expect($rotated)->toBeApiSuccess();

        $replay = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ], ['Idempotency-Key' => 'refresh-replay']);
        expect($replay)->toBeApiError(ErrorCode::TokenExpired);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_the_same_idempotency_key_does_not_create_two_sessions(): void
    {
        $account = Account::factory()->create(['email' => 'player@example.test', 'password' => 'password']);
        $payload = ['email' => $account->email, 'password' => 'password'];

        $first = $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => 'same-key']);
        $second = $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => 'same-key']);

        expect($first)->toBeApiSuccess();
        expect($second)->toBeApiSuccess();
        $second->assertExactJson($first->json());
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('device_sessions', 1);
    }
}
