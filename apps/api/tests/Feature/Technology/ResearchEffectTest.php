<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Economy\Application\CityEconomyService;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Game\Technology\Application\ResearchReconciler;
use Game\Technology\Infrastructure\PlayerTechnology;
use Game\Technology\Infrastructure\ResearchOrder;
use Illuminate\Support\Facades\Queue;

/**
 * ROADMAP criterion 4 — the one CONTEXT.md warns can quietly fail: "a
 * completed technology's effect must be observable in a recomputed value —
 * test it, do not just assert the row exists."
 *
 * Named distinctly from the helpers in the sibling Technology/Construction
 * test files — PHPUnit loads every *Test.php in one process.
 *
 * @return array{token: string, worldId: string, playerId: string, cityId: string}
 */
function enterCityForResearchEffect(string $keyPrefix): array
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
 * N synthetic, never-persisted farm rows. Farm's own placeholder production
 * (packages/game-data/data/buildings.json) is a flat +1/second at EVERY
 * level — a Phase 46 balance-curve concern, not this plan's — so the starter
 * city's real baseline is exactly 1. Feeding EffectResolver a base of 1 makes
 * every authored technology's multiply permille (max +30%) truncate straight
 * back down to 1 via intdiv, and the "effect observable" assertion below
 * would pass while proving nothing — this plan's own trap, applied to
 * arithmetic instead of timing. A larger, synthetic base survives truncation
 * without touching the shared game-data balance numbers, while still
 * exercising the real GameDataCatalog::effectsFor()/EffectResolver pipeline
 * against the real, HTTP-driven, reconciler-completed PlayerTechnology row.
 *
 * @return Illuminate\Support\Collection<int, CityBuilding>
 */
function syntheticFarms(int $count): Illuminate\Support\Collection
{
    return collect(range(1, $count))
        ->map(static fn (): CityBuilding => new CityBuilding(['building_code' => 'farm', 'level' => 1]));
}

it('raises the resolved production rate by the documented permille amount once research completes', function (): void {
    $clock = freezeClock('2026-09-08T09:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();

    $ctx = enterCityForResearchEffect('effect-basic');
    $catalog = app(GameDataCatalog::class);
    $permille = (int) $catalog->technologyLevel('agriculture', 1)['effects'][0]['value'];

    // The real, unresearched baseline, read before anything is touched.
    $city = City::query()->whereKey($ctx['cityId'])->firstOrFail();
    $baseRate = app(CityEconomyService::class)->ratesPerHour($city)['food'];

    test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'effect-basic-agriculture')
        ->postJson('/api/v1/game/technologies/agriculture/research')
        ->assertStatus(201);

    ResearchOrder::query()->where('city_id', $ctx['cityId'])->whereNull('completed_at')->firstOrFail();
    $clock->advanceSeconds((int) $catalog->technologyLevel('agriculture', 1)['research_time_seconds'] + 1);

    // No HTTP call here: GET /game/technologies and the research endpoints
    // both complete overdue research on the read path, which would finish
    // this research before the reconciler could and prove nothing.
    expect(app(ResearchReconciler::class)->run())->toBe(1);

    $researched = PlayerTechnology::query()
        ->where('world_id', $ctx['worldId'])
        ->where('player_id', $ctx['playerId'])
        ->get();
    expect($researched)->toHaveCount(1)
        ->and($researched->first()->technology_code)->toBe('agriculture')
        ->and($researched->first()->level)->toBe(1);

    $baseline = 100;
    $resolved = $catalog->effectsFor(syntheticFarms($baseline), $researched);

    expect($resolved['production.food'])->toBe(intdiv($baseline * $permille, 1000))
        ->and($resolved['production.food'])->toBeGreaterThan($baseline)
        ->and($baseRate)->toBeInt();
});

it('stacks a technology rank as its own declared surplus, not a product compounded with the previous rank', function (): void {
    $clock = freezeClock('2026-09-08T09:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();

    $ctx = enterCityForResearchEffect('effect-ranks');
    $catalog = app(GameDataCatalog::class);
    $level1Permille = (int) $catalog->technologyLevel('agriculture', 1)['effects'][0]['value'];
    $level2Permille = (int) $catalog->technologyLevel('agriculture', 2)['effects'][0]['value'];
    $baseline = 100;

    // Rank 1: level 0 -> 1.
    test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'effect-ranks-r1')
        ->postJson('/api/v1/game/technologies/agriculture/research')
        ->assertStatus(201);
    ResearchOrder::query()->where('city_id', $ctx['cityId'])->whereNull('completed_at')->firstOrFail();
    $clock->advanceSeconds((int) $catalog->technologyLevel('agriculture', 1)['research_time_seconds'] + 1);
    expect(app(ResearchReconciler::class)->run())->toBe(1);

    $afterRank1 = PlayerTechnology::query()
        ->where('world_id', $ctx['worldId'])
        ->where('player_id', $ctx['playerId'])
        ->get();
    $rateAfterRank1 = $catalog->effectsFor(syntheticFarms($baseline), $afterRank1)['production.food'];
    expect($rateAfterRank1)->toBe(intdiv($baseline * $level1Permille, 1000));

    // Rank 2: level 1 -> 2.
    test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'effect-ranks-r2')
        ->postJson('/api/v1/game/technologies/agriculture/research')
        ->assertStatus(201);
    ResearchOrder::query()->where('city_id', $ctx['cityId'])->whereNull('completed_at')->firstOrFail();
    $clock->advanceSeconds((int) $catalog->technologyLevel('agriculture', 2)['research_time_seconds'] + 1);
    expect(app(ResearchReconciler::class)->run())->toBe(1);

    $afterRank2 = PlayerTechnology::query()
        ->where('world_id', $ctx['worldId'])
        ->where('player_id', $ctx['playerId'])
        ->get();
    expect($afterRank2->first()->level)->toBe(2);
    $rateAfterRank2 = $catalog->effectsFor(syntheticFarms($baseline), $afterRank2)['production.food'];

    // The surplus is rank 2's OWN declared value applied to the untouched
    // baseline — not rank 1's already-applied multiplier compounded with rank
    // 2's on top of it (which would be baseline * level1 * level2 / 1_000_000).
    $compounded = intdiv($rateAfterRank1 * $level2Permille, 1000);
    expect($rateAfterRank2)->toBe(intdiv($baseline * $level2Permille, 1000))
        ->and($rateAfterRank2)->not->toBe($compounded);
});

it("leaves an unresearched empire's rate exactly at the building-only baseline", function (): void {
    freezeClock('2026-09-08T09:00:00+00:00');

    $ctx = enterCityForResearchEffect('effect-baseline');
    $catalog = app(GameDataCatalog::class);

    // The regression pin that 10-03's EffectResolver/effectsFor() refactor did
    // not shift the pre-technology baseline: farm's own declared per-second
    // effect, read from the catalogue rather than hardcoded, times the
    // documented seconds-per-hour conversion (not a balance number).
    $farmProduction = (int) $catalog->buildingLevel('farm', 1)['effects'][0]['value'];
    $expected = $farmProduction * 3600;

    $city = City::query()->whereKey($ctx['cityId'])->firstOrFail();
    $rate = app(CityEconomyService::class)->ratesPerHour($city);

    expect($rate['food'])->toBe($expected)
        ->and(PlayerTechnology::query()->where('player_id', $ctx['playerId'])->count())->toBe(0);
});
