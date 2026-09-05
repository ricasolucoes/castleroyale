<?php

declare(strict_types=1);

namespace Game\Economy\Infrastructure;

use Game\Economy\Domain\LedgerParty;
use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * @property string $id
 * @property string $world_id
 * @property string $city_id
 * @property string $source
 * @property string $destination
 * @property string $resource
 * @property int $amount
 * @property int $overflow_amount
 * @property string $reason
 * @property string|null $reference
 * @property int $economy_version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EconomyLedger extends Model
{
    use HasGameUlid;

    protected $table = 'economy_ledger';

    protected $fillable = [
        'world_id', 'city_id', 'source', 'destination', 'resource', 'amount',
        'overflow_amount', 'reason', 'reference', 'economy_version',
    ];

    /**
     * The only sanctioned way to write a ledger row.
     *
     * Both parties are required arguments rather than optional attributes, so a new
     * mutation site cannot forget them the way it could forget an array key. An
     * architecture test forbids `EconomyLedger::create(` anywhere else.
     *
     * `overflow_amount` on a credit row is the portion the warehouse cap refused to
     * let through. It is deliberately NOT a second row to `system:void`: the parties
     * describe the intended flow, and criterion 5 reconciles balances by summing
     * `amount` alone.
     */
    public static function record(
        string $worldId,
        string $cityId,
        LedgerParty $source,
        LedgerParty $destination,
        string $resource,
        int $amount,
        int $overflowAmount,
        string $reason,
        ?string $reference,
        int $economyVersion,
    ): self {
        return self::create([
            'world_id' => $worldId,
            'city_id' => $cityId,
            'source' => $source->value(),
            'destination' => $destination->value(),
            'resource' => $resource,
            'amount' => $amount,
            'overflow_amount' => $overflowAmount,
            'reason' => $reason,
            'reference' => $reference,
            'economy_version' => $economyVersion,
        ]);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'overflow_amount' => 'integer',
            'economy_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Append-only (08-CONTEXT.md): a corrected balance is a new compensating row,
        // never an edited history. Raw DB::table writes bypass this on purpose — the
        // schema migration's backfill needs exactly that.
        self::updating(static function (): void {
            throw new RuntimeException('economy_ledger is append-only; a correction is a new row.');
        });

        self::deleting(static function (): void {
            throw new RuntimeException('economy_ledger is append-only; rows are never deleted.');
        });
    }
}
