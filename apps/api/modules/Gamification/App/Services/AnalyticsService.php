<?php

declare(strict_types=1);

namespace Game\Gamification\App\Services;

use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    public function track(string $eventName, array $properties = []): void
    {
        // Integration with external analytics provider
        Log::info("Analytics Event: {$eventName}", $properties);
    }
}
