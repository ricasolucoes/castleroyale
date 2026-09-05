<?php

declare(strict_types=1);

namespace Game\Economy\Domain;

use InvalidArgumentException;

/**
 * One end of a resource movement.
 *
 * Half the counterparties in this economy are not rows — production, the starter
 * grant and the construction sink are systemic — so a nullable foreign key would
 * have to mean four different things. A typed `{kind}:{identifier}` string keeps
 * every end nameable and greppable, and keeps a party from becoming free-form text.
 */
final readonly class LedgerParty
{
    private function __construct(public string $value) {}

    public static function city(string $cityId): self
    {
        if ($cityId === '') {
            throw new InvalidArgumentException('A city ledger party needs a city id.');
        }

        return new self('city:'.$cityId);
    }

    public static function system(string $name): self
    {
        if (preg_match('/^[a-z][a-z0-9_.]{0,50}$/', $name) !== 1) {
            throw new InvalidArgumentException(
                'A system ledger party must be lower_snake_case: "'.$name.'" is not.',
            );
        }

        return new self('system:'.$name);
    }

    public function value(): string
    {
        return $this->value;
    }
}
