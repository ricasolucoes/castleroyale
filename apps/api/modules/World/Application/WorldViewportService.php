<?php

declare(strict_types=1);

namespace Game\World\Application;

use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Illuminate\Support\Facades\DB;

final readonly class WorldViewportService
{
    public function __construct(private GameBootstrapService $bootstrap) {}

    /**
     * @return array{player: array<string, mixed>, world: array<string, mixed>, bounds: array{min_x: int, max_x: int, min_y: int, max_y: int}, tiles: list<array{id: string, region_id: string, x: int, y: int, terrain: string}>}
     */
    public function handle(Account $account, int $minX, int $maxX, int $minY, int $maxY): array
    {
        $width = $maxX - $minX + 1;
        $height = $maxY - $minY + 1;
        $limit = max(1, (int) config('game.limits.world_viewport_max_tiles', 4096));
        if ($width < 1 || $height < 1 || $width > intdiv($limit, max(1, $height))) {
            throw GameException::of(
                ErrorCode::ValidationFailed,
                'The requested viewport exceeds the allowed tile limit.',
                ['max_tiles' => $limit],
            );
        }

        $bootstrap = $this->bootstrap->handle($account);
        $worldId = (string) $bootstrap['world']['id'];
        $tileQuery = DB::table('tiles')->where('world_id', $worldId);
        $tileQuery
            ->whereBetween('x', [$minX, $maxX])
            ->whereBetween('y', [$minY, $maxY])
            ->orderBy('y')
            ->orderBy('x');
        $tiles = $tileQuery->get(['id', 'region_id', 'x', 'y', 'terrain'])
            ->map(static fn (object $tile): array => [
                'id' => (string) $tile->id,
                'region_id' => (string) $tile->region_id,
                'x' => (int) $tile->x,
                'y' => (int) $tile->y,
                'terrain' => (string) $tile->terrain,
            ])
            ->all();

        return [
            'player' => $bootstrap['player'],
            'world' => $bootstrap['world'],
            'bounds' => ['min_x' => $minX, 'max_x' => $maxX, 'min_y' => $minY, 'max_y' => $maxY],
            'tiles' => array_values($tiles),
        ];
    }
}
