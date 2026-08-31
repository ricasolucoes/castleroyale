<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Game\Identity\Domain\DeviceSession;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Idempotency\IdempotencyService;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDeviceSession
{
    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $accessToken = $user->currentAccessToken();
        if ($accessToken === null) {
            return $next($request);
        }

        $session = DeviceSession::query()->where('token_id', $accessToken->id)->first();

        if ($session !== null && $session->revoked_at !== null) {
            $replay = $this->idempotency->replayIfCompleted($request);
            if ($replay !== null) {
                return $replay;
            }

            $accessToken->delete();

            return ApiResponse::error(ErrorCode::DeviceSessionRevoked, 'Device session has been revoked.');
        }

        return $next($request);
    }
}
