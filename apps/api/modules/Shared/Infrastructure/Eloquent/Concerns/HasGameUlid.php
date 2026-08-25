<?php

declare(strict_types=1);

namespace Game\Shared\Infrastructure\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * ULID primary keys for client-addressable game entities.
 *
 * Pairs with GameTable::entity(). Kept as one trait so no model has to remember
 * to also flip $keyType and $incrementing — forgetting either silently produces a
 * model that cannot find its own row.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasGameUlid
{
    use HasUlids;

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
