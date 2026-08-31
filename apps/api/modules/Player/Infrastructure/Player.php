<?php

declare(strict_types=1);

namespace Game\Player\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $world_id
 * @property string $account_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Player extends Model
{
    use HasGameUlid;

    protected $table = 'players';

    protected $fillable = ['world_id', 'account_id', 'name'];
}
