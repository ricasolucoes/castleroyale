<?php

declare(strict_types=1);

namespace App\Providers;

use Game\Identity\Domain\SocialIdentityVerifier;
use Game\Identity\Infrastructure\OidcIdentityVerifier;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\Time\SystemClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Game rules resolve time through the Clock contract, never `now()`.
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(SocialIdentityVerifier::class, OidcIdentityVerifier::class);
    }

    public function boot(): void
    {
        // Mass assignment is opt-in per model; an unguarded model is how a
        // player ends up setting their own `gold` column. See threat model.
        Model::preventSilentlyDiscardingAttributes();
        Model::preventAccessingMissingAttributes(! $this->app->environment('production'));

        // Lazy loading is an N+1 waiting to happen on a world-map query.
        Model::preventLazyLoading(! $this->app->environment('production'));

        // Every persisted instant is UTC (ADR-006 / docs/backend/architecture.md).
        // APP_TIMEZONE pins the framework clock; this pins anything reaching
        // for bare PHP date functions, so the two can never disagree.
        date_default_timezone_set('UTC');

        if (! $this->app->environment('production')) {
            DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $query): void {
                if ($query->time > 200) {
                    logger()->warning('Slow query', [
                        'sql' => $query->sql,
                        'time_ms' => $query->time,
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }
    }
}
