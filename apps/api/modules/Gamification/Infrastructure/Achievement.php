<?php

declare(strict_types=1);

namespace Game\Gamification\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class Achievement extends Model
{
    protected $table = 'gamification_achievements';

    protected $fillable = [
        'google_play_id',
        'internal_id',
        'name',
        'description',
        'category',
        'xp_reward',
        'is_incremental',
        'max_steps',
        'is_hidden',
    ];

    protected $casts = [
        'is_incremental' => 'boolean',
        'is_hidden' => 'boolean',
    ];
}
