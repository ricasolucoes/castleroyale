<?php

declare(strict_types=1);

namespace Tests\Feature\Economy;

use Game\City\Application\CityStateService;
use Game\City\Infrastructure\City;
use Game\Economy\Application\CityEconomyService;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Economy\ResourceType;
use Illuminate\Support\Facades\DB;

it('produces the same balance whether a city is read hourly or after a long absence', function (): void {
    $clock = freezeClock('2026-08-28T00:00:00+00:00');
    $first = Account::factory()->create();
    $second = Account::factory()->create();
    $firstBootstrap = app(GameBootstrapService::class)->handle($first);
    $secondBootstrap = app(GameBootstrapService::class)->handle($second);

    $clock->advanceSeconds(21600);
    $firstState = app(CityStateService::class)->handle($first);

    $clock->advanceSeconds(-21600);
    for ($minute = 1; $minute <= 360; $minute++) {
        $clock->advanceSeconds(60);
        app(CityStateService::class)->handle($second);
    }
    $secondState = app(CityStateService::class)->handle($second);

    expect($firstState['resources']['current'])->toEqual($secondState['resources']['current'])
        ->and($firstBootstrap['city']['id'])->not->toBe($secondBootstrap['city']['id']);
});

it('caps server grants and records discarded overflow in the ledger', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $city = City::query()
        ->where('world_id', $bootstrap['world']['id'])
        ->whereKey($bootstrap['city']['id'])
        ->firstOrFail();

    $result = DB::transaction(function () use ($city): array {
        $query = City::query()->where('world_id', $city->world_id)->whereKey($city->getKey());
        $query->getQuery()->lockForUpdate();
        $locked = $query->firstOrFail();

        return app(CityEconomyService::class)->creditLocked(
            $locked,
            ResourceBundle::fromArray(['food' => 700]),
            'test.grant',
            'economy-foundation-test',
        );
    });

    expect($result['credited']->toArray()['food'])->toBe(500)
        ->and($result['overflow']->toArray()['food'])->toBe(200)
        ->and(EconomyLedger::query()
            ->where('world_id', $city->world_id)
            ->where('city_id', $city->getKey())
            ->where('resource', 'food')
            ->where('reason', 'test.grant')
            ->where('reference', 'economy-foundation-test')
            ->value('overflow_amount'))->toBe(200);
});

it('reconciles every city balance to the append-only ledger after a sequence of operations', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $cityId = $bootstrap['city']['id'];
    $worldId = $bootstrap['world']['id'];
    $economy = app(CityEconomyService::class);

    foreach (range(1, 40) as $step) {
        DB::transaction(function () use ($economy, $worldId, $cityId, $step): void {
            $query = City::query()->where('world_id', $worldId)->whereKey($cityId);
            $query->getQuery()->lockForUpdate();
            $city = $query->firstOrFail();
            $resource = ResourceType::all()[$step % count(ResourceType::all())]->value;
            $amount = ($step * 37) % 121;

            if ($step % 2 === 0) {
                $economy->creditLocked(
                    $city,
                    ResourceBundle::fromArray([$resource => $amount]),
                    'test.sequence.credit',
                    'step-'.$step,
                );

                return;
            }

            $available = (int) $city->getAttribute($resource);
            $debit = min($available, $amount);
            $economy->debitLocked(
                $city,
                ResourceBundle::fromArray([$resource => $debit]),
                'test.sequence.debit',
                'step-'.$step,
            );
        });
    }

    $city = City::query()->where('world_id', $worldId)->whereKey($cityId)->firstOrFail();
    $ledgerBalances = EconomyLedger::query()
        ->where('world_id', $worldId)
        ->where('city_id', $cityId)
        ->get()
        ->groupBy('resource')
        ->map(static fn ($rows): int => (int) $rows->sum('amount'));

    foreach (ResourceType::all() as $resource) {
        expect($ledgerBalances->get($resource->value, 0))
            ->toBe((int) $city->getAttribute($resource->value));
    }
});
