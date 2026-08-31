<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Game\Identity\Domain\Account;
use Game\Identity\Domain\DeviceSession;
use Game\Shared\Application\Error\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_list_and_revoke_only_their_sessions(): void
    {
        $account = Account::factory()->create(['email' => 'player@example.test', 'password' => 'password']);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $account->email,
            'password' => 'password',
        ], [
            'Idempotency-Key' => 'login-1',
            'X-Device-Id' => 'phone-1',
        ]);
        $token = $login->json('data.access_token');
        $session = DeviceSession::query()->firstOrFail();

        $list = $this->withBearer($token)->getJson('/api/v1/auth/sessions');
        expect($list)->toBeApiSuccess();
        $list->assertJsonPath('data.0.id', $session->id);

        $revoked = $this->withBearer($token)->deleteJson('/api/v1/auth/sessions/'.$session->id, [], [
            'Idempotency-Key' => 'revoke-1',
        ]);
        expect($revoked)->toBeApiSuccess();

        $this->assertNotNull($session->fresh()->revoked_at);
        $blocked = $this->withBearer($token)->getJson('/api/v1/auth/sessions');
        expect($blocked)->toBeApiError(ErrorCode::DeviceSessionRevoked);
    }

    public function test_revoke_is_idempotent(): void
    {
        $account = Account::factory()->create(['email' => 'player@example.test', 'password' => 'password']);
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $account->email,
            'password' => 'password',
        ], ['Idempotency-Key' => 'login-1'])->json('data.access_token');
        $session = DeviceSession::query()->firstOrFail();
        $headers = ['Idempotency-Key' => 'revoke-1'];

        $first = $this->withBearer($token)->deleteJson('/api/v1/auth/sessions/'.$session->id, [], $headers);
        $second = $this->withBearer($token)->deleteJson('/api/v1/auth/sessions/'.$session->id, [], $headers);

        expect($first)->toBeApiSuccess();
        expect($second)->toBeApiSuccess();
        $second->assertExactJson($first->json());
    }

    public function test_session_limit_evicts_the_least_recently_used_session(): void
    {
        config(['game.auth.max_device_sessions' => 2]);
        $account = Account::factory()->create(['email' => 'player@example.test', 'password' => 'password']);

        foreach (['one', 'two', 'three'] as $index => $deviceId) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $account->email,
                'password' => 'password',
            ], [
                'Idempotency-Key' => 'guest-'.$index,
                'X-Device-Id' => $deviceId,
            ]);
            DeviceSession::query()->where('device_id', $deviceId)->update([
                'last_seen_at' => Carbon::now()->subMinutes(3 - $index),
            ]);
        }

        $this->assertDatabaseCount('device_sessions', 2);
        $this->assertDatabaseHas('device_sessions', ['device_id' => 'two']);
        $this->assertDatabaseHas('device_sessions', ['device_id' => 'three']);
        $this->assertDatabaseMissing('device_sessions', ['device_id' => 'one']);
    }

    private function withBearer(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
