<?php

declare(strict_types=1);

namespace Game\Economy\Application;

use DateTimeImmutable;
use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Economy\ResourceType;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;

final readonly class CityEconomyService
{
    public function __construct(private GameDataCatalog $catalog) {}

    public function accrueLocked(City $city, DateTimeImmutable $now): void
    {
        $this->syncDerivedStatsLocked($city);

        $lastAccruedAt = $city->last_accrued_at;
        if ($lastAccruedAt === null) {
            $city->setAttribute('last_accrued_at', $now);
            $city->save();

            return;
        }

        $elapsedSeconds = max(0, $now->getTimestamp() - $lastAccruedAt->toDateTimeImmutable()->getTimestamp());
        if ($elapsedSeconds === 0) {
            if ($city->isDirty()) {
                $city->save();
            }

            return;
        }

        $effects = $this->effects($city);
        foreach (ResourceType::all() as $resource) {
            $name = $resource->value;
            $produced = (int) ($effects['production.'.$name] ?? 0) * $elapsedSeconds;
            $current = (int) $city->getAttribute($name);
            $capacity = (int) $city->getAttribute($name.'_capacity');
            $credited = min($produced, max(0, $capacity - $current));
            $overflow = max(0, $produced - $credited);

            if ($credited > 0) {
                $city->setAttribute($name, $current + $credited);
            }

            if ($credited > 0 || $overflow > 0) {
                EconomyLedger::create([
                    'world_id' => $city->world_id,
                    'city_id' => $city->getKey(),
                    'resource' => $name,
                    'amount' => $credited,
                    'overflow_amount' => $overflow,
                    'reason' => 'production.elapsed',
                    'reference' => $city->getKey(),
                    'economy_version' => (int) config('game.versions.economy', 1),
                ]);
            }
        }

        $city->setAttribute('last_accrued_at', $now);
        $city->save();
    }

    public function debitLocked(City $city, ResourceBundle $cost, string $reason, string $reference): void
    {
        $balances = $this->balances($city);
        if (! ResourceBundle::fromArray($balances)->covers($cost)) {
            throw GameException::of(
                ErrorCode::InsufficientResources,
                'Not enough resources.',
                ['missing' => array_map(static fn ($resource): string => $resource->value, ResourceBundle::fromArray($balances)->shortfallAgainst($cost))],
            );
        }

        foreach ($cost->toArray() as $resource => $amount) {
            if ($amount === 0) {
                continue;
            }

            $city->setAttribute($resource, $balances[$resource] - $amount);
            EconomyLedger::create([
                'world_id' => $city->world_id,
                'city_id' => $city->getKey(),
                'resource' => $resource,
                'amount' => -$amount,
                'overflow_amount' => 0,
                'reason' => $reason,
                'reference' => $reference,
                'economy_version' => (int) config('game.versions.economy', 1),
            ]);
        }

        $city->save();
    }

    /**
     * Credit a server-authorised grant while preserving the warehouse cap.
     *
     * Overflow is deliberately persisted in the same ledger row as the
     * credited amount so an operator can distinguish a capped faucet from a
     * missing mutation.
     *
     * @return array{credited: ResourceBundle, overflow: ResourceBundle}
     */
    public function creditLocked(City $city, ResourceBundle $grant, string $reason, string $reference): array
    {
        $balances = $this->balances($city);
        $credited = [];
        $overflow = [];

        foreach ($grant->toArray() as $resource => $amount) {
            $current = $balances[$resource];
            $capacity = (int) $city->getAttribute($resource.'_capacity');
            $accepted = min($amount, max(0, $capacity - $current));
            $discarded = $amount - $accepted;

            $credited[$resource] = $accepted;
            $overflow[$resource] = $discarded;

            if ($accepted > 0) {
                $city->setAttribute($resource, $current + $accepted);
            }

            if ($accepted > 0 || $discarded > 0) {
                EconomyLedger::create([
                    'world_id' => $city->world_id,
                    'city_id' => $city->getKey(),
                    'resource' => $resource,
                    'amount' => $accepted,
                    'overflow_amount' => $discarded,
                    'reason' => $reason,
                    'reference' => $reference,
                    'economy_version' => (int) config('game.versions.economy', 1),
                ]);
            }
        }

        if ($city->isDirty()) {
            $city->save();
        }

        return [
            'credited' => ResourceBundle::fromArray($credited),
            'overflow' => ResourceBundle::fromArray($overflow),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function balances(City $city): array
    {
        return $this->resourceAttributes($city, '');
    }

    /**
     * @return array<string, int>
     */
    public function capacities(City $city): array
    {
        return $this->resourceAttributes($city, '_capacity');
    }

    /**
     * @return array<string, int>
     */
    private function resourceAttributes(City $city, string $suffix): array
    {
        $values = [];
        foreach (ResourceType::all() as $resource) {
            $values[$resource->value] = (int) $city->getAttribute($resource->value.$suffix);
        }

        return $values;
    }

    private function syncDerivedStatsLocked(City $city): void
    {
        $starterCapacity = $this->catalog->starterValues('capacity');
        $effects = $this->effects($city);

        foreach (ResourceType::all() as $resource) {
            $name = $resource->value;
            $capacity = $starterCapacity[$name] + (int) ($effects['storage.'.$name] ?? 0);
            if ((int) $city->getAttribute($name.'_capacity') !== $capacity) {
                $city->setAttribute($name.'_capacity', $capacity);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function effects(City $city): array
    {
        $buildings = CityBuilding::query()
            ->where('world_id', $city->world_id)
            ->where('city_id', $city->getKey())
            ->get();

        return $this->catalog->effectsForBuildings($buildings);
    }
}
