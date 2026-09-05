<?php

declare(strict_types=1);

namespace Tests\Feature\Economy;

use Game\Economy\Domain\LedgerParty;
use Game\Economy\Infrastructure\EconomyLedger;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Replays a full guest -> bootstrap -> read -> upgrade session and returns
 * every economy_ledger row it wrote, plus the world and city id the session
 * played in. Shared because tests 1 and 2 both reason about the same session
 * from two different angles (every row vs. a specific direction per reason).
 *
 * @return array{0: Collection<int, EconomyLedger>, 1: string, 2: string}
 */
function playLedgerAuditSession(string $suffix): array
{
    $clock = freezeClock('2026-08-28T00:00:00+00:00');

    $guest = test()->withHeader('Idempotency-Key', 'ledger-audit-guest-'.$suffix)
        ->postJson('/api/v1/auth/guest');
    $tokens = $guest->json('data');

    $bootstrap = test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ledger-audit-bootstrap-'.$suffix)
        ->postJson('/api/v1/game/bootstrap');
    $worldId = $bootstrap->json('data.world.id');
    $cityId = $bootstrap->json('data.city.id');

    test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');

    $clock->advanceSeconds(600);

    test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ledger-audit-upgrade-'.$suffix)
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    $rows = EconomyLedger::query()->where('world_id', $worldId)->get();

    return [$rows, $worldId, $cityId];
}

it('names both parties on every ledger row a real gameplay session writes', function (): void {
    [$rows, , $cityId] = playLedgerAuditSession('session-1');

    expect($rows)->not->toBeEmpty();
    foreach ($rows as $row) {
        expect($row->source)->not->toBe('')
            ->and($row->destination)->not->toBe('')
            ->and($row->source)->not->toBe('system:legacy')
            ->and($row->destination)->not->toBe('system:legacy')
            ->and([$row->source, $row->destination])->toContain('city:'.$row->city_id);
    }

    expect($rows->pluck('reason')->unique()->sort()->values()->all())
        ->toContain('starter.grant', 'production.elapsed', 'building.upgrade');

    // The row's own city_id, not the session's outer $cityId, is what "belongs to
    // this city" means — every row is scoped to itself, never to another city.
    expect($rows->pluck('city_id')->unique()->all())->toBe([$cityId]);
});

it('records the documented direction for each reason', function (): void {
    [$rows, , $cityId] = playLedgerAuditSession('session-2');

    $starter = $rows->firstWhere('reason', 'starter.grant');
    expect($starter)->not->toBeNull()
        ->and($starter->source)->toBe('system:starter')
        ->and($starter->destination)->toBe('city:'.$cityId);

    $production = $rows->firstWhere('reason', 'production.elapsed');
    expect($production)->not->toBeNull()
        ->and($production->source)->toBe('system:production')
        ->and($production->destination)->toBe('city:'.$cityId);

    $spend = $rows->firstWhere('reason', 'building.upgrade');
    expect($spend)->not->toBeNull()
        ->and($spend->source)->toBe('city:'.$cityId)
        ->and($spend->destination)->toBe('system:construction')
        ->and($spend->amount)->toBeLessThan(0);
});

it('refuses to update or delete a ledger row', function (): void {
    [$rows] = playLedgerAuditSession('session-3');
    $row = $rows->first();
    $original = (int) $row->amount;

    $updateFailed = false;
    try {
        $row->update(['amount' => 999]);
    } catch (RuntimeException) {
        $updateFailed = true;
    }

    $deleteFailed = false;
    try {
        $row->delete();
    } catch (RuntimeException) {
        $deleteFailed = true;
    }

    expect($updateFailed)->toBeTrue()
        ->and($deleteFailed)->toBeTrue()
        ->and((int) EconomyLedger::query()->whereKey($row->getKey())->value('amount'))->toBe($original);
});

it('rejects a malformed system party', function (): void {
    expect(LedgerParty::system('production')->value())->toBe('system:production')
        ->and(LedgerParty::city('01J')->value())->toBe('city:01J')
        ->and(fn () => LedgerParty::system('Not Valid'))->toThrow(InvalidArgumentException::class);
});
