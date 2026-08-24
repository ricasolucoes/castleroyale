<?php

declare(strict_types=1);

namespace Game\Shared\Domain\Economy;

/**
 * An immutable basket of resources, used for costs, yields, payloads and
 * plunder. Missing entries are treated as zero, so a bundle is always total
 * over {@see ResourceType}.
 */
final class ResourceBundle
{
    /**
     * @param array<string, ResourceAmount> $amounts keyed by ResourceType value
     */
    private function __construct(
        private readonly array $amounts,
    ) {}

    /**
     * @param array<string, int> $raw keyed by ResourceType value
     */
    public static function fromArray(array $raw): self
    {
        $amounts = [];

        foreach (ResourceType::all() as $type) {
            $amounts[$type->value] = ResourceAmount::of($raw[$type->value] ?? 0);
        }

        return new self($amounts);
    }

    public static function empty(): self
    {
        return self::fromArray([]);
    }

    public static function single(ResourceType $type, int $value): self
    {
        return self::fromArray([$type->value => $value]);
    }

    public function get(ResourceType $type): ResourceAmount
    {
        return $this->amounts[$type->value];
    }

    public function plus(self $other): self
    {
        $result = [];

        foreach (ResourceType::all() as $type) {
            $result[$type->value] = $this->get($type)->plus($other->get($type))->value;
        }

        return self::fromArray($result);
    }

    /**
     * @throws \Game\Shared\Domain\Exception\InvalidResourceAmount when any component would go negative
     */
    public function minus(self $other): self
    {
        $result = [];

        foreach (ResourceType::all() as $type) {
            $result[$type->value] = $this->get($type)->minus($other->get($type))->value;
        }

        return self::fromArray($result);
    }

    /**
     * Whether this bundle covers the full cost of `$cost`.
     *
     * Always call this before {@see minus()} inside a locked transaction —
     * checking outside the lock is the classic double-spend window.
     */
    public function covers(self $cost): bool
    {
        foreach (ResourceType::all() as $type) {
            if (! $this->get($type)->isAtLeast($cost->get($type))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The resource types this bundle is short of, given a cost.
     *
     * Returned to the client inside the error `details` so the UI can point at
     * exactly which counter is red.
     *
     * @return list<ResourceType>
     */
    public function shortfallAgainst(self $cost): array
    {
        $missing = [];

        foreach (ResourceType::all() as $type) {
            if (! $this->get($type)->isAtLeast($cost->get($type))) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    public function isEmpty(): bool
    {
        foreach ($this->amounts as $amount) {
            if (! $amount->isZero()) {
                return false;
            }
        }

        return true;
    }

    public function scaledByPermille(int $permille): self
    {
        $result = [];

        foreach (ResourceType::all() as $type) {
            $result[$type->value] = $this->get($type)->scaledByPermille($permille)->value;
        }

        return self::fromArray($result);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->amounts as $key => $amount) {
            $result[$key] = $amount->value;
        }

        return $result;
    }
}
