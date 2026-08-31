<?php

declare(strict_types=1);

namespace Game\Gamification\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Game\Player\Domain\Models\Player;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlayerAchievement extends Model
{
    protected $table = 'gamification_player_achievements';

    protected $fillable = [
        'player_id',
        'achievement_id',
        'current_steps',
        'unlocked_at',
        'synced_with_google_play_at'
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'synced_with_google_play_at' => 'datetime'
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
