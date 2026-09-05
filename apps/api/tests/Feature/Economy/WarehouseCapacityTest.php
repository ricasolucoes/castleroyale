<?php

declare(strict_types=1);

namespace Tests\Feature\Economy;

use Game\City\Application\CityStateService;
use Game\City\Infrastructure\City;
use Game\Economy\Application\CityEconomyService;
use Game\Economy\Domain\LedgerParty;
use Game\Economy\Domain\OverflowPolicy;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Economy\ResourceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('refuses a strict grant the warehouse cannot hold and changes nothing', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $city = City::query()
        ->where('world_id', $bootstrap['world']['id'])
        ->whereKey($bootstrap['city']['id'])
        ->firstOrFail();

    $before = (int) $city->food;
    $ledgerBefore = EconomyLedger::query()->where('city_id', $city->getKey())->count();

    $exception = null;

    try {
        DB::transaction(function () use ($city): array {
            $query = City::query()->where('world_id', $city->world_id)->whereKey($city->getKey());
            $query->getQuery()->lockForUpdate();
            $locked = $query->firstOrFail();

            return app(CityEconomyService::class)->creditLocked(
                $locked,
                ResourceBundle::fromArray(['food' => 700]),
                'test.strict_grant',
                'warehouse-capacity-test',
                LedgerParty::system('test_strict_grant'),
                OverflowPolicy::Refuse,
            );
        });
    } catch (GameException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(GameException::class)
        ->and($exception?->errorCode)->toBe(ErrorCode::WarehouseCapacityExceeded)
        ->and($exception?->details['exceeded'])->toBe(['food'])
        ->and((int) $city->fresh()->food)->toBe($before)
        ->and(EconomyLedger::query()->where('city_id', $city->getKey())->count())->toBe($ledgerBefore);
});

it('accepts a strict grant that fills the warehouse exactly to capacity', function (): void {
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
            ResourceBundle::fromArray(['food' => 500]),
            'test.strict_grant_exact',
            'warehouse-capacity-test',
            LedgerParty::system('test_strict_grant'),
            OverflowPolicy::Refuse,
        );
    });

    expect($result['credited']->toArray()['food'])->toBe(500)
        ->and($result['overflow']->toArray()['food'])->toBe(0)
        ->and((int) $city->fresh()->food)->toBe((int) $city->fresh()->food_capacity);
});

it('lands elapsed production exactly on the cap and records the discarded tail', function (): void {
    $clock = freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $worldId = $bootstrap['world']['id'];
    $cityId = $bootstrap['city']['id'];

    app(CityStateService::class)->handle($account);
    $clock->advanceSeconds(21600);
    $state = app(CityStateService::class)->handle($account);

    expect($state['resources']['current']['food'])->toBe($state['resources']['capacity']['food'])
        ->and($state['resources']['current']['food'])->toBeLessThanOrEqual($state['resources']['capacity']['food']);

    $rows = EconomyLedger::query()
        ->where('world_id', $worldId)->where('city_id', $cityId)
        ->where('resource', 'food')->where('reason', 'production.elapsed')->get();
    expect((int) $rows->sum('overflow_amount'))->toBeGreaterThan(0);

    foreach (ResourceType::all() as $resource) {
        expect($state['resources']['current'][$resource->value])
            ->toBeLessThanOrEqual($state['resources']['capacity'][$resource->value]);
    }
});

it('renders WAREHOUSE_CAPACITY_EXCEEDED through the API error envelope', function (): void {
    // No gameplay endpoint performs a strict credit until Phase 16 (gathering
    // returns), so this throwaway route asserts the transport contract the
    // criterion names — the GameException render funnel in bootstrap/app.php —
    // without inventing a game endpoint this phase does not own.
    Route::get('/api/v1/__warehouse-capacity-probe', static function (): void {
        throw GameException::of(
            ErrorCode::WarehouseCapacityExceeded,
            'The warehouse cannot hold this delivery.',
            ['exceeded' => ['food']],
        );
    });

    $response = $this->getJson('/api/v1/__warehouse-capacity-probe');

    expect($response)->toBeApiError(ErrorCode::WarehouseCapacityExceeded);
    $response->assertStatus(400)->assertJsonPath('error.details.exceeded', ['food']);
});
