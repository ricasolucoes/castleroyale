<?php

declare(strict_types=1);

namespace Tests\Feature\Economy;

use Game\City\Application\CityStateService;
use Game\City\Infrastructure\City;
use Game\Economy\Application\CityEconomyService;
use Game\Economy\Domain\LedgerParty;
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
            LedgerParty::system('test_grant'),
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

it('reconciles every city balance to the append-only ledger over a random operation sequence', function (): void {
    // A random test without a reproducible seed is a flaky test. Export
    // ECONOMY_PROPERTY_SEED=<n> to replay the exact sequence a red run produced.
    $seed = (int) (getenv('ECONOMY_PROPERTY_SEED') ?: random_int(1, 2_147_483_647));
    mt_srand($seed);

    $clock = freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $cityId = $bootstrap['city']['id'];
    $worldId = $bootstrap['world']['id'];
    $economy = app(CityEconomyService::class);
    $resources = ResourceType::all();

    foreach (range(1, 120) as $step) {
        $operation = mt_rand(0, 2);   // 0 = credit, 1 = debit, 2 = advance time
        $resource = $resources[mt_rand(0, count($resources) - 1)]->value;
        $amount = mt_rand(0, 400);
        $seconds = mt_rand(1, 900);

        DB::transaction(function () use (
            $economy, $worldId, $cityId, $step, $operation, $resource, $amount, $seconds, $clock
        ): void {
            $query = City::query()->where('world_id', $worldId)->whereKey($cityId);
            $query->getQuery()->lockForUpdate();
            $city = $query->firstOrFail();

            if ($operation === 2) {
                $clock->advanceSeconds($seconds);
                $economy->accrueLocked($city, $clock->now());

                return;
            }

            if ($operation === 0) {
                $economy->creditLocked(
                    $city,
                    ResourceBundle::fromArray([$resource => $amount]),
                    'test.sequence.credit',
                    'step-'.$step,
                    LedgerParty::system('test_faucet'),
                );

                return;
            }

            $available = (int) $city->getAttribute($resource);
            $economy->debitLocked(
                $city,
                ResourceBundle::fromArray([$resource => min($available, $amount)]),
                'test.sequence.debit',
                'step-'.$step,
                LedgerParty::system('test_sink'),
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

    foreach ($resources as $resource) {
        $stored = (int) $city->getAttribute($resource->value);
        $summed = $ledgerBalances->get($resource->value, 0);

        // PHPUnit's assertion is used rather than expect()->toBe() because it is the
        // one guaranteed to carry the seed in the failure message.
        $this->assertSame(
            $stored,
            $summed,
            sprintf(
                'Ledger sum for %s does not reproduce the balance. Replay with ECONOMY_PROPERTY_SEED=%d',
                $resource->value,
                $seed,
            ),
        );

        $this->assertGreaterThanOrEqual(0, $stored, "Negative balance for {$resource->value}; seed {$seed}");
        $this->assertLessThanOrEqual(
            (int) $city->getAttribute($resource->value.'_capacity'),
            $stored,
            "Balance above capacity for {$resource->value}; seed {$seed}",
        );
    }
});
