<?php

declare(strict_types=1);

namespace Game\Alliance\Application;

use Game\Alliance\Infrastructure\Alliance;
use Game\Alliance\Infrastructure\AllianceMember;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class AllianceStateService
{
    public function __construct(private Clock $clock, private GameBootstrapService $bootstrap) {}

    /** @return array<string, mixed> */
    public function handle(Account $account): array
    {
        return DB::transaction(fn (): array => $this->state($this->bootstrap->handle($account)));
    }

    /** @return array<string, mixed> */
    public function create(Account $account, string $name, string $tag): array
    {
        $bootstrap = $this->bootstrap->handle($account);
        $worldId = $bootstrap['world']['id'];
        $playerId = $bootstrap['player']['id'];
        $cleanName = trim($name);
        $cleanTag = strtoupper(trim($tag));

        try {
            return DB::transaction(function () use ($bootstrap, $worldId, $playerId, $cleanName, $cleanTag): array {
                $membershipQuery = AllianceMember::query()
                    ->where('world_id', $worldId)
                    ->where('player_id', $playerId);
                $membershipQuery->getQuery()->lockForUpdate();
                $existingMembership = $membershipQuery->first();
                if ($existingMembership !== null) {
                    throw GameException::of(ErrorCode::AlreadyInAlliance, 'The player is already in an alliance.');
                }

                $allianceQuery = Alliance::query()
                    ->where('world_id', $worldId)
                    ->where(static function (Builder $query) use ($cleanName, $cleanTag): void {
                        $query->where('name', $cleanName)->orWhere('tag', $cleanTag);
                    });
                $allianceQuery->getQuery()->lockForUpdate();
                $existingAlliance = $allianceQuery->first();
                if ($existingAlliance !== null) {
                    throw GameException::of(ErrorCode::AllianceNameTaken, 'The alliance name or tag is already in use.');
                }

                $alliance = Alliance::create([
                    'world_id' => $worldId,
                    'leader_player_id' => $playerId,
                    'name' => $cleanName,
                    'tag' => $cleanTag,
                    'member_count' => 1,
                    'max_members' => max(1, (int) config('game.limits.max_alliance_members')),
                ]);
                AllianceMember::create([
                    'world_id' => $worldId,
                    'alliance_id' => $alliance->getKey(),
                    'player_id' => $playerId,
                    'role' => 'leader',
                ]);

                return $this->state($bootstrap);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw GameException::of(ErrorCode::AllianceNameTaken, 'The alliance name or tag is already in use.');
            }

            throw $exception;
        }
    }

    /**
     * @param array{player: array<string, mixed>, world: array<string, mixed>, city: array<string, mixed>} $bootstrap
     * @return array<string, mixed>
     */
    private function state(array $bootstrap): array
    {
        $worldId = (string) $bootstrap['world']['id'];
        $playerId = (string) $bootstrap['player']['id'];
        $membership = AllianceMember::query()
            ->where('world_id', $worldId)
            ->where('player_id', $playerId)
            ->first();
        $alliance = $membership === null ? null : Alliance::query()
            ->where('world_id', $worldId)
            ->whereKey($membership->alliance_id)
            ->first();
        $members = [];

        if ($alliance !== null) {
            $memberRowsQuery = AllianceMember::query()
                ->where('world_id', $worldId)
                ->where('alliance_id', $alliance->getKey());
            $memberRowsQuery->getQuery()->orderBy('created_at');
            $memberRows = $memberRowsQuery->get();

            foreach ($memberRows as $member) {
                $memberPlayer = Player::query()
                    ->where('world_id', $worldId)
                    ->whereKey($member->player_id)
                    ->first();
                if ($memberPlayer === null) {
                    continue;
                }

                $members[] = [
                    'player_id' => (string) $memberPlayer->getKey(),
                    'player_name' => (string) $memberPlayer->name,
                    'role' => (string) $member->role,
                ];
            }
        }

        return [
            'player' => $bootstrap['player'],
            'world' => $bootstrap['world'],
            'alliance' => $alliance === null ? null : [
                'id' => (string) $alliance->getKey(),
                'name' => (string) $alliance->name,
                'tag' => (string) $alliance->tag,
                'role' => $membership === null ? 'member' : (string) $membership->role,
                'member_count' => (int) $alliance->member_count,
                'max_members' => (int) $alliance->max_members,
            ],
            'members' => $members,
            'can_create' => $membership === null,
            'server_time' => $this->clock->now()->format(DATE_ATOM),
        ];
    }
}
