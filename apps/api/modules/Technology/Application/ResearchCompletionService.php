<?php

declare(strict_types=1);

namespace Game\Technology\Application;

use DateTimeImmutable;
use Game\City\Interface\Broadcasting\CityStateChanged;
use Game\Player\Infrastructure\Player;
use Game\Technology\Infrastructure\PlayerTechnology;
use Game\Technology\Infrastructure\ResearchOrder;
use Illuminate\Support\Collection;

/**
 * Completes every overdue open research for a player.
 *
 * Lives in this earlier wave rather than with the research *start* logic
 * (10-05) because `ResearchService::start()` must call this before deciding
 * whether a player is already busy — a player whose research finished but
 * whose job has not run yet must not be told RESEARCH_IN_PROGRESS. Putting
 * completion here makes that dependency a real one rather than a circular one.
 */
final class ResearchCompletionService
{
    public function completeOverdueLocked(Player $player, DateTimeImmutable $now): void
    {
        $ordersQuery = ResearchOrder::query()
            ->where('world_id', $player->world_id)
            ->where('player_id', $player->getKey())
            ->where('finishes_at', '<=', $now);
        $ordersQuery->getQuery()->whereNull('completed_at')->lockForUpdate();
        /** @var Collection<int, ResearchOrder> $orders */
        $orders = $ordersQuery->get();

        foreach ($orders as $order) {
            $technologyQuery = PlayerTechnology::query()
                ->where('world_id', $player->world_id)
                ->where('player_id', $player->getKey())
                ->where('technology_code', $order->technology_code);
            $technologyQuery->getQuery()->lockForUpdate();
            /** @var PlayerTechnology|null $playerTechnology */
            $playerTechnology = $technologyQuery->first();

            if ($playerTechnology !== null) {
                $playerTechnology->update(['level' => $order->target_level]);
            } else {
                PlayerTechnology::create([
                    'world_id' => $player->world_id,
                    'player_id' => $player->getKey(),
                    'technology_code' => $order->technology_code,
                    'level' => $order->target_level,
                ]);
            }

            $order->forceFill(['completed_at' => $now])->save();

            // A player-scoped realtime channel does not exist yet (Phase 33's
            // concern). Broadcasting on the order's city channel is the
            // deliberate interim — the client is already subscribed to it for
            // construction — not an oversight.
            CityStateChanged::dispatch(
                (string) $order->city_id,
                (string) $player->world_id,
                $now->format(DATE_ATOM),
            );
        }
    }
}
