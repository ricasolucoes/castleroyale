<?php

declare(strict_types=1);

namespace Game\Gamification\Infrastructure;

use Game\Player\Infrastructure\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlayerAchievement extends Model
{
    protected $table = 'gamification_player_achievements';

    protected $fillable = [
        'player_id',
        'achievement_id',
        'current_steps',
        'unlocked_at',
        'synced_with_google_play_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'synced_with_google_play_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Achievement, $this>
     */
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
