<?php

declare(strict_types=1);

namespace Game\Economy\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $world_id
 * @property string $city_id
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
        'world_id', 'city_id', 'resource', 'amount', 'overflow_amount',
        'reason', 'reference', 'economy_version',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'overflow_amount' => 'integer',
            'economy_version' => 'integer',
        ];
    }
}
