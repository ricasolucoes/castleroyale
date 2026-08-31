<?php

declare(strict_types=1);

namespace Game\Alliance\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $world_id
 * @property string $leader_player_id
 * @property string $name
 * @property string $tag
 * @property int $member_count
 * @property int $max_members
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Alliance extends Model
{
    use HasGameUlid;

    protected $table = 'alliances';

    protected $fillable = [
        'world_id', 'leader_player_id', 'name', 'tag', 'member_count', 'max_members',
    ];

    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
            'max_members' => 'integer',
        ];
    }
}
