<?php

declare(strict_types=1);

namespace Game\Shared\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every API request with a correlation id and echoes it back.
 *
 * The same id is attached to log records, queued jobs and broadcast events so
 * a single player action can be followed from the tap on the phone through
 * the job that completes it. Accepts an inbound `X-Request-Id` so the mobile
 * client can correlate its own traces, but always sanitises it.
 */
final class AttachRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $inbound = (string) $request->header('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $inbound) === 1
            ? $inbound
            : (string) Str::ulid();

        $request->attributes->set('request_id', $requestId);

        Log::shareContext([
            'request_id' => $requestId,
            'client_version' => $request->header('X-Client-Version'),
            'platform' => $request->header('X-Client-Platform'),
        ]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
