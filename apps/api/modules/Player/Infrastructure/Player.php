<?php

declare(strict_types=1);

namespace Game\Player\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

final class Player extends Model
{
    use HasGameUlid;

    protected $table = 'players';

    protected $fillable = ['world_id', 'account_id', 'name'];
}
