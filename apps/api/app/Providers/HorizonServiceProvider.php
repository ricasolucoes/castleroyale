<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::night();
    }

    /**
     * Who may look at the queues.
     *
     * Horizon exposes job payloads, which for this game include player ids,
     * resource deltas and battle seeds. It is never public: outside local it
     * requires an authenticated staff account. See docs/operations/observability.md.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user): bool {
            if ($this->app->environment('local')) {
                return true;
            }

            return $user?->is_staff === true;
        });
    }
}
