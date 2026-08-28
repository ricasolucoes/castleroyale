<?php

declare(strict_types=1);

use Game\Identity\Domain\Account;
use Game\Player\Infrastructure\Player;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
|
| Channel names follow `<scope>.<id>` and every one of them is private. There
| is no public channel in this game: knowing which regions are busy is itself
| intelligence, so world traffic is scoped to the regions a player can
| legitimately observe.
|
| The authorisation callbacks below are deliberately conservative placeholders
| — they deny by default until the module that owns each scope lands and can
| answer the question properly:
|
|   player.{playerId}        GSD Phase 04
|   city.{cityId}            GSD Phase 07
|   alliance.{allianceId}    GSD Phase 22
|   battle.{battleId}        GSD Phase 17
|   world.{worldId}.region.{regionId}   GSD Phase 05
|
| See docs/realtime/architecture.md and docs/realtime/events.md.
|
*/

Broadcast::channel('player.{playerId}', static function (Account $account, string $playerId): bool {
    $playerQuery = Player::query()
        ->whereKey($playerId)
        ->where('account_id', $account->getKey());

    return $playerQuery->getQuery()->exists();
});

Broadcast::channel('city.{cityId}', static function (Account $account, string $cityId): bool {
    // Phase 07: the viewer must own the city, or be reinforcing it.
    return false;
});

Broadcast::channel('alliance.{allianceId}', static function (Account $account, string $allianceId): bool {
    // Phase 22: membership check plus per-channel alliance permission.
    return false;
});

Broadcast::channel('battle.{battleId}', static function (Account $account, string $battleId): bool {
    // Phase 17: participant, reinforcer, or alliance spectator.
    return false;
});

Broadcast::channel('world.{worldId}.region.{regionId}', static function (
    Account $account,
    string $worldId,
    string $regionId,
): bool {
    // Phase 05: the player must belong to the world and have the region
    // inside their subscribed viewport.
    return false;
});
