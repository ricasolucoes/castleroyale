<?php

declare(strict_types=1);

namespace Game\Gamification\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Game\Player\Domain\Models\Player;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Progression extends Model
{
    protected $table = 'gamification_progressions';

    protected $fillable = [
        'player_id',
        'level',
        'xp',
        'current_streak',
        'longest_streak',
        'last_login_at'
    ];

    protected $casts = [
        'last_login_at' => 'datetime'
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
