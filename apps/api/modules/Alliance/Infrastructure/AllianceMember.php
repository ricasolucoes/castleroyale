<?php

declare(strict_types=1);

namespace Game\Alliance\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $world_id
 * @property string $alliance_id
 * @property string $player_id
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AllianceMember extends Model
{
    use HasGameUlid;

    protected $table = 'alliance_members';

    protected $fillable = ['world_id', 'alliance_id', 'player_id', 'role'];
}
