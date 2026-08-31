<?php

declare(strict_types=1);

namespace Game\World\Application;

use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Facades\DB;

final readonly class WorldStateService
{
    public function __construct(private Clock $clock, private GameBootstrapService $bootstrap) {}

    /** @return array<string, mixed> */
    public function handle(Account $account): array
    {
        $bootstrap = $this->bootstrap->handle($account);
        $worldId = $bootstrap['world']['id'];
        $playerId = $bootstrap['player']['id'];
        $centerX = $bootstrap['city']['x'];
        $centerY = $bootstrap['city']['y'];
        $radius = max(0, (int) config('game.limits.world_view_radius'));

        $cities = DB::table('cities')
            ->where('world_id', $worldId)
            ->whereBetween('x', [$centerX - $radius, $centerX + $radius])
            ->whereBetween('y', [$centerY - $radius, $centerY + $radius])
            ->orderBy('x')
            ->orderBy('y')
            ->get(['id', 'name_key', 'x', 'y', 'player_id'])
            ->map(static fn (object $city): array => [
                'id' => (string) $city->id,
                'name_key' => (string) $city->name_key,
                'x' => (int) $city->x,
                'y' => (int) $city->y,
                'is_player_city' => (string) $city->player_id === $playerId,
            ])
            ->values()
            ->all();

        return [
            'player' => $bootstrap['player'],
            'world' => $bootstrap['world'],
            'center' => ['x' => $centerX, 'y' => $centerY],
            'radius' => $radius,
            'cities' => $cities,
            'server_time' => $this->clock->now()->format(DATE_ATOM),
        ];
    }
}
