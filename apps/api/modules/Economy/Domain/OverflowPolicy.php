<?php

declare(strict_types=1);

namespace Game\Economy\Domain;

/**
 * What a credit does when the warehouse cannot hold all of it.
 *
 * A passive faucet (elapsed-time production) fills to the cap and discards the
 * rest — that is a normal economic ceiling, not a failure. An explicit transfer
 * asked for an exact amount, so delivering a fraction of it would be a lie; it is
 * refused whole with WAREHOUSE_CAPACITY_EXCEEDED.
 */
enum OverflowPolicy: string
{
    case DiscardAtCap = 'discard_at_cap';
    case Refuse = 'refuse';
}
