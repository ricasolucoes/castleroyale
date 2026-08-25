<?php

declare(strict_types=1);

use App\Models\User;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Domain\Time\FrozenClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test bootstrapping
|--------------------------------------------------------------------------
|
| Feature tests get the full application and a fresh database. Unit tests
| exercise the pure domain and must not need either — if a test under
| tests/Unit starts needing the container, it belongs in Feature.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Architecture');

/*
|--------------------------------------------------------------------------
| PostgreSQL-only tests
|--------------------------------------------------------------------------
|
| Loaded only by phpunit.postgres.xml — the default suite's testsuites
| do not include this directory. See .planning/codebase/TESTING.md.
|
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Postgres');

/*
|--------------------------------------------------------------------------
| Custom expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeApiSuccess', function (int $status = 200) {
    /** @var Illuminate\Testing\TestResponse $this */
    $this->value->assertStatus($status)->assertJsonStructure(['data']);

    return $this;
});

expect()->extend('toBeApiError', function (ErrorCode $code, ?int $status = null) {
    /** @var Illuminate\Testing\TestResponse $this */
    $this->value
        ->assertStatus($status ?? $code->httpStatus())
        ->assertJsonPath('error.code', $code->value)
        ->assertJsonMissingPath('data');

    return $this;
});

/*
|--------------------------------------------------------------------------
| Fixtures
|--------------------------------------------------------------------------
|
| Shared setup every phase needs. Anything copy-pasted into a third test file
| belongs here instead.
|
*/

/**
 * Pin the server clock. Every duration in this game is anchored to the injected
 * Clock, so freezing it is how timed gameplay becomes deterministic (ADR-006).
 */
function freezeClock(string $iso8601 = '2026-01-01T00:00:00+00:00'): FrozenClock
{
    $clock = FrozenClock::at($iso8601);

    app()->instance(Clock::class, $clock);

    return $clock;
}

/**
 * Authenticate as a back-office account.
 */
function actingAsStaff(?User $user = null): User
{
    $user ??= User::factory()->staff()->create();

    test()->actingAs($user);

    return $user;
}
