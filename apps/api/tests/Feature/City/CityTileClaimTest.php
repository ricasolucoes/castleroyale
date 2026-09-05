<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('refuses a second claim on an occupied tile', function (): void {
    $world = World::create([
        'code' => 'tile-claim-precheck',
        'name' => 'Tile Claim Precheck',
        'capacity' => 10,
        'is_open' => true,
    ]);
    $first = Account::factory()->create();
    $second = Account::factory()->create();

    app(GameBootstrapService::class)->handle($first, (string) $world->getKey(), 'First Claim');
    $world->forceFill(['spawn_index' => 0])->save();

    $exception = null;
    try {
        app(GameBootstrapService::class)->handle($second, (string) $world->getKey(), 'Second Claim');
    } catch (GameException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(GameException::class)
        ->and($exception?->errorCode)->toBe(ErrorCode::TileOccupied);
});

it('resolves a simultaneous claim on the same tile to exactly one city', function (): void {
    $world = World::create([
        'code' => 'tile-claim-race',
        'name' => 'Tile Claim Race',
        'capacity' => 10,
        'is_open' => true,
    ]);
    $first = Account::factory()->create();
    $second = Account::factory()->create();

    app(GameBootstrapService::class)->handle($first, (string) $world->getKey(), 'Race First');

    $rival = Player::create([
        'world_id' => $world->getKey(),
        'account_id' => Account::factory()->create()->getKey(),
        'name' => 'Race Rival',
    ]);

    // The dispatcher is rebuilt per test by the framework, so this listener does
    // not leak into the rest of the suite.
    $raced = false;
    Event::listen('eloquent.creating: '.City::class, function (City $city) use (&$raced, $rival): void {
        if ($raced) {
            return;
        }
        $raced = true;

        DB::table('cities')->insert([
            'id' => (string) Str::ulid(),
            'world_id' => $city->world_id,
            'player_id' => $rival->getKey(),
            'name_key' => 'city.starter_name',
            'x' => $city->x,
            'y' => $city->y,
            'last_accrued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $exception = null;
    try {
        app(GameBootstrapService::class)->handle($second, (string) $world->getKey(), 'Race Second');
    } catch (GameException $caught) {
        $exception = $caught;
    }

    expect($raced)->toBeTrue()
        ->and($exception)->toBeInstanceOf(GameException::class)
        ->and($exception?->errorCode)->toBe(ErrorCode::TileOccupied)
        ->and(City::query()->where('world_id', $world->getKey())->count())->toBe(1);
});
