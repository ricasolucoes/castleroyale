<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $actor_user_id
 * @property string $action
 * @property string $target_type
 * @property string|null $target_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string $reason
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AdminAudit extends Model
{
    protected $fillable = [
        'actor_user_id',
        'action',
        'target_type',
        'target_id',
        'before',
        'after',
        'reason',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
