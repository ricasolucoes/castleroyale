<?php

declare(strict_types=1);

namespace Tests\Feature\Economy;

use Game\City\Infrastructure\City;
use Game\Construction\Application\BuildingUpgradeService;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Economy\Application\CityEconomyService;
use Game\Economy\Domain\LedgerParty;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Identity\Domain\Account;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Economy\ResourceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('resolves two competing spends for the same resources to one success and one INSUFFICIENT_RESOURCES', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');

    // Guest + bootstrap over HTTP so the whole request pipeline is under test,
    // not just the service.
    $guest = $this->withHeader('Idempotency-Key', 'economy-race-guest')
        ->postJson('/api/v1/auth/guest');
    $token = (string) $guest->json('data.access_token');

    $this->withToken($token)->getJson('/api/v1/game/city')->assertOk();

    $account = Account::query()->firstOrFail();          // the guest just created
    $city = City::query()->firstOrFail();
    $worldId = (string) $city->world_id;
    $cityId = (string) $city->getKey();

    // farm L2 costs wood 120 + stone 60; lumber_mill L2 costs food 80 + stone 100.
    // Either fits alone; together they need 160 stone and only 100 exists. This is
    // the contention the criterion asks for, expressed in real game-data costs.
    //
    // The starter grant left the city at food/wood/stone 500 and iron/gold 250/100
    // (packages/game-data/data/starter.json). Bringing it down to the contested
    // balances goes through debitLocked, not a raw DB::table write, so this setup
    // step also lands in the ledger — otherwise the "sum reconciles the balance
    // exactly" assertion below would fail for reasons that have nothing to do with
    // the race being proven.
    DB::transaction(function () use ($cityId, $worldId): void {
        $query = City::query()->where('world_id', $worldId)->whereKey($cityId);
        $query->getQuery()->lockForUpdate();
        $locked = $query->firstOrFail();

        app(CityEconomyService::class)->debitLocked(
            $locked,
            ResourceBundle::fromArray(['food' => 420, 'wood' => 380, 'stone' => 400, 'iron' => 250, 'gold' => 100]),
            'test.setup',
            'economy-race-setup',
            LedgerParty::system('test_setup'),
        );
    });

    // The rival commits between the request's own bootstrap transaction (which
    // reads Player and COMMITS) and start()'s locked City read. Firing on the
    // bootstrap read is what makes the rival's write survive to be seen — a
    // listener placed inside start()'s transaction would be rolled back with it.
    //
    // The dispatcher is rebuilt per test by the framework, so this listener does
    // not leak into the rest of the suite (same technique as CityTileClaimTest).
    $raced = false;
    Event::listen('eloquent.retrieved: '.Player::class, function () use (&$raced, $account, $worldId, $cityId): void {
        if ($raced) {
            return;
        }
        $raced = true;

        app(BuildingUpgradeService::class)->start(
            $account, $worldId, $cityId, 'lumber_mill', 'economy-race-rival',
        );
    });

    $loser = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'economy-race-loser')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    expect($raced)->toBeTrue();
    expect($loser)->toBeApiError(ErrorCode::InsufficientResources);

    // Exactly one success.
    expect(ConstructionOrder::query()->where('world_id', $worldId)->count())->toBe(1)
        ->and((string) ConstructionOrder::query()->where('world_id', $worldId)->value('building_code'))
        ->toBe('lumber_mill');

    // The loser left nothing behind — no partial debit, no orphan ledger row.
    $spendRows = EconomyLedger::query()
        ->where('world_id', $worldId)
        ->where('reason', 'building.upgrade')
        ->get();
    expect($spendRows->pluck('reference')->unique()->values()->all())->toBe(['economy-race-rival']);

    // No value minted or destroyed by the race.
    $fresh = City::query()->whereKey($cityId)->firstOrFail();
    expect((int) $fresh->stone)->toBe(0);
    foreach (ResourceType::all() as $resource) {
        $ledgerTotal = (int) EconomyLedger::query()
            ->where('world_id', $worldId)->where('city_id', $cityId)
            ->where('resource', $resource->value)->sum('amount');
        expect((int) $fresh->getAttribute($resource->value))
            ->toBe($ledgerTotal)
            ->toBeGreaterThanOrEqual(0);
    }
});
