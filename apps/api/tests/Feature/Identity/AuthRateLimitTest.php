<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Game\Shared\Application\Error\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_logins_are_limited_by_ip_and_identifier(): void
    {
        config(['game.auth.rate_limit_per_minute' => 10]);
        RateLimiter::clear('127.0.0.1|unknown@example.test');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'unknown@example.test',
                'password' => 'wrong-password',
            ], ['Idempotency-Key' => 'attempt-'.$attempt]);
            expect($response)->toBeApiError(ErrorCode::InvalidCredentials);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.test',
            'password' => 'wrong-password',
        ], ['Idempotency-Key' => 'attempt-11']);
        expect($response)->toBeApiError(ErrorCode::RateLimited);
    }
}
