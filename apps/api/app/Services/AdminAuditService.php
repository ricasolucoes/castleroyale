<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AdminAuditService
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record(
        string $action,
        string $targetType,
        string|int|null $targetId,
        ?array $before,
        ?array $after,
        string $reason,
    ): AdminAudit {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for every administrative action.',
            ]);
        }

        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        return AdminAudit::create([
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_address' => request()->ip(),
        ]);
    }
}
