<?php

declare(strict_types=1);

namespace Game\Gamification\App\Providers;

use Illuminate\Support\ServiceProvider;
use Game\Gamification\App\Services\GamificationService;

class GamificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GamificationService::class, function ($app) {
            return new GamificationService();
        });
    }

    public function boot(): void
    {
        // Load migrations if they were here, but we put them in apps/api/database/migrations
    }
}
