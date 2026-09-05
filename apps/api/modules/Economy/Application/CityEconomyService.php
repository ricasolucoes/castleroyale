<?php

declare(strict_types=1);

namespace Game\Economy\Application;

use DateTimeImmutable;
use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Economy\Domain\OverflowPolicy;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Economy\ResourceType;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;

final readonly class CityEconomyService
{
    private const int SECONDS_PER_HOUR = 3600;

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
     * @param OverflowPolicy $policy `DiscardAtCap` (default) fills to the cap and
     *                               discards the rest, for passive faucets like elapsed-time production.
     *                               `Refuse` is all-or-nothing: an explicit transfer asked for an exact
     *                               amount and cannot have it, so it is refused before any mutation.
     * @return array{credited: ResourceBundle, overflow: ResourceBundle}
     */
    public function creditLocked(
        City $city,
        ResourceBundle $grant,
        string $reason,
        string $reference,
        OverflowPolicy $policy = OverflowPolicy::DiscardAtCap,
    ): array {
        $balances = $this->balances($city);

        if ($policy === OverflowPolicy::Refuse) {
            // Checked before the first mutation so the refusal is all-or-nothing —
            // a half-delivered transfer is the duplication bug this error exists to
            // prevent, not a lesser version of success.
            $exceeded = [];
            foreach ($grant->toArray() as $resource => $amount) {
                if ((int) $city->getAttribute($resource.'_capacity') < $balances[$resource] + $amount) {
                    $exceeded[] = $resource;
                }
            }

            if ($exceeded !== []) {
                throw GameException::of(
                    ErrorCode::WarehouseCapacityExceeded,
                    'The warehouse cannot hold this delivery.',
                    ['exceeded' => $exceeded],
                );
            }
        }

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
     * Game data authors production per second; the wire contract publishes it per
     * hour so the client can interpolate against a wall clock without re-deriving
     * the unit (08-UI-SPEC.md § Data Contract Dependency). The multiplication is
     * exact — no float, no rounding (ADR-010).
     *
     * @return array<string, int>
     */
    public function ratesPerHour(City $city): array
    {
        $effects = $this->effects($city);
        $rates = [];
        foreach (ResourceType::all() as $resource) {
            $rates[$resource->value] = (int) ($effects['production.'.$resource->value] ?? 0) * self::SECONDS_PER_HOUR;
        }

        return $rates;
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
