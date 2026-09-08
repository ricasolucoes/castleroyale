<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Shared\Application\Error\ErrorCode;
use Game\Technology\Infrastructure\PlayerTechnology;
use Game\Technology\Infrastructure\ResearchOrder;

/**
 * Guest + bootstrap, returning the tokens and ids every test here needs.
 *
 * Named distinctly from the helpers in the sibling Technology/Construction
 * test files — PHPUnit loads them all into one process, and a top-level
 * function name shared with a sibling file is a fatal redeclare (the trap
 * that bit Phase 09).
 *
 * @return array{token: string, worldId: string, playerId: string, cityId: string}
 */
function enterCityForResearchQueue(string $keyPrefix): array
{
    $guest = test()->withHeader('Idempotency-Key', $keyPrefix.'-guest')
        ->postJson('/api/v1/auth/guest');
    $guest->assertStatus(201);
    $token = (string) $guest->json('data.access_token');

    $bootstrap = test()->withToken($token)
        ->withHeader('Idempotency-Key', $keyPrefix.'-bootstrap')
        ->postJson('/api/v1/game/bootstrap');
    $bootstrap->assertStatus(201);

    return [
        'token' => $token,
        'worldId' => (string) $bootstrap->json('data.world.id'),
        'playerId' => (string) $bootstrap->json('data.player.id'),
        'cityId' => (string) $bootstrap->json('data.city.id'),
    ];
}

/**
 * A test fixture setting a player's technology *level* directly is not a
 * resource mutation — same reasoning as Construction's setBuildingLevel() — so
 * a raw model write is the right tool, not the research endpoint (which would
 * itself be gated by the very rule this fixture needs to bypass).
 */
function setPlayerTechnologyLevel(string $worldId, string $playerId, string $technologyCode, int $level): void
{
    PlayerTechnology::updateOrCreate(
        ['world_id' => $worldId, 'player_id' => $playerId, 'technology_code' => $technologyCode],
        ['level' => $level],
    );
}

it('starts a research, debiting the city and stamping the injected clock in UTC', function (): void {
    freezeClock('2026-09-08T12:34:56+00:00');
    config(['game.time_scale' => 1]);

    $ctx = enterCityForResearchQueue('research-utc');
    $before = City::query()->whereKey($ctx['cityId'])->firstOrFail();

    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-utc-agriculture')
        ->postJson('/api/v1/game/technologies/agriculture/research');
    $response->assertStatus(201);

    $order = ResearchOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // agriculture level 1 is 30s in the catalogue, at time_scale 1.
    expect($order->started_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:34:56+00:00')
        ->and($order->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:35:26+00:00');

    // The client is never handed a zone-ambiguous timestamp.
    $response->assertJsonPath('data.research.started_at', '2026-09-08T12:34:56+00:00')
        ->assertJsonPath('data.research.finishes_at', '2026-09-08T12:35:26+00:00');

    // agriculture level 1 costs wood 80, stone 50.
    $after = City::query()->whereKey($ctx['cityId'])->firstOrFail();
    expect($before->wood - $after->wood)->toBe(80)
        ->and($before->stone - $after->stone)->toBe(50);

    $ledgerRows = EconomyLedger::query()
        ->where('reason', 'technology.research')
        ->where('reference', 'research-utc-agriculture')
        ->get();

    expect($ledgerRows)->toHaveCount(2)
        ->and($ledgerRows->pluck('resource')->sort()->values()->all())->toBe(['stone', 'wood']);
});

it('refuses a second concurrent research with RESEARCH_IN_PROGRESS', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForResearchQueue('research-busy');

    test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-busy-agriculture')
        ->postJson('/api/v1/game/technologies/agriculture/research')
        ->assertStatus(201);

    $ledgerCountBefore = EconomyLedger::query()->count();
    $balancesBefore = City::query()->whereKey($ctx['cityId'])->firstOrFail()
        ->only(['food', 'wood', 'stone', 'iron', 'gold']);

    $second = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-busy-mining')
        ->postJson('/api/v1/game/technologies/mining/research');

    expect($second)->toBeApiError(ErrorCode::ResearchInProgress);
    $second->assertStatus(400);

    // No matter which other technology is attempted, the one open research
    // refuses every one of them, not just the specific technology in flight.
    $third = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-busy-ironworking')
        ->postJson('/api/v1/game/technologies/ironworking/research');

    expect($third)->toBeApiError(ErrorCode::ResearchInProgress);

    expect(ResearchOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(1)
        ->and(EconomyLedger::query()->count())->toBe($ledgerCountBefore)
        ->and(City::query()->whereKey($ctx['cityId'])->firstOrFail()->only(['food', 'wood', 'stone', 'iron', 'gold']))
        ->toBe($balancesBefore);
});

it('refuses a locked technology and names the missing prerequisite', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForResearchQueue('research-locked');

    // tactics requires weaponsmithing level 1, never researched here.
    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-locked-tactics')
        ->postJson('/api/v1/game/technologies/tactics/research');

    expect($response)->toBeApiError(ErrorCode::TechnologyLocked);
    $response->assertJsonPath('error.details.missing.weaponsmithing', 1);

    expect(ResearchOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(0);
});

it('refuses a maxed technology with TECHNOLOGY_MAX_LEVEL', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForResearchQueue('research-maxed');
    setPlayerTechnologyLevel($ctx['worldId'], $ctx['playerId'], 'agriculture', 3);

    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-maxed-agriculture')
        ->postJson('/api/v1/game/technologies/agriculture/research');

    expect($response)->toBeApiError(ErrorCode::TechnologyMaxLevel);
    $response->assertStatus(400);

    expect(ResearchOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(0);
});

it('reports TECHNOLOGY_MAX_LEVEL over an unmet prerequisite', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForResearchQueue('research-max-wins');
    // tactics requires weaponsmithing level 1, never granted here — but tactics
    // is already at its own max level, and the permanent "cannot ever go
    // higher" answer must win over the structural one.
    setPlayerTechnologyLevel($ctx['worldId'], $ctx['playerId'], 'tactics', 3);

    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-max-wins-tactics')
        ->postJson('/api/v1/game/technologies/tactics/research');

    expect($response)->toBeApiError(ErrorCode::TechnologyMaxLevel);
});

it('reports TECHNOLOGY_LOCKED over RESEARCH_IN_PROGRESS', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForResearchQueue('research-locked-wins');

    test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-locked-wins-agriculture')
        ->postJson('/api/v1/game/technologies/agriculture/research')
        ->assertStatus(201);

    // A research is already open (agriculture) — but tactics is ALSO locked on
    // its own unmet prerequisite. The permanent reason must win over the
    // transient one.
    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-locked-wins-tactics')
        ->postJson('/api/v1/game/technologies/tactics/research');

    expect($response)->toBeApiError(ErrorCode::TechnologyLocked);
    $response->assertStatus(400);
});

it('ignores a client-supplied cost, duration and target level', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');
    config(['game.time_scale' => 1]);

    $ctx = enterCityForResearchQueue('research-client');
    $before = City::query()->whereKey($ctx['cityId'])->firstOrFail();

    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'research-client-agriculture')
        ->postJson('/api/v1/game/technologies/agriculture/research', [
            'research_time_seconds' => 0,
            'cost' => [],
            'target_level' => 99,
        ]);
    $response->assertStatus(201);

    $order = ResearchOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // Exactly what the server would have computed with no body at all.
    expect($order->target_level)->toBe(1)
        ->and($order->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T13:00:30+00:00');

    // The catalogue's cost was debited, not the client's zeroes.
    $after = City::query()->whereKey($ctx['cityId'])->firstOrFail();
    expect($before->wood - $after->wood)->toBe(80)
        ->and($before->stone - $after->stone)->toBe(50);
});
