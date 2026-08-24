<?php

declare(strict_types=1);

namespace Game\Platform\Interface\Http;

use Game\Shared\Domain\Time\Clock;
use Game\Shared\Interface\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Liveness and readiness for the API.
 *
 * `/api/v1/health` is unauthenticated and cheap: it is polled by the load
 * balancer and by the mobile client's connectivity banner. It never leaks
 * infrastructure detail beyond up/down per dependency.
 */
final class HealthController
{
    public function __construct(
        private readonly Clock $clock,
    ) {}

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(static fn () => DB::select('select 1')),
            'cache' => $this->check(static fn () => cache()->set('health:ping', 1, 5)),
        ];

        if (config('queue.default') === 'redis' || config('cache.default') === 'redis') {
            $checks['redis'] = $this->check(static fn () => Redis::connection()->command('ping', []));
        }

        $healthy = ! in_array(false, $checks, true);

        return ApiResponse::success(
            data: [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
            ],
            meta: [
                'game' => config('game.name'),
                'environment' => config('app.env'),
                'server_time' => $this->clock->now()->format(DATE_ATOM),
                'versions' => config('game.versions'),
            ],
            status: $healthy ? 200 : 503,
        );
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
