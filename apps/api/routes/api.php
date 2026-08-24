<?php

declare(strict_types=1);

use Game\Platform\Interface\Http\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Every client-facing endpoint is versioned under `/api/v1`. Route files are
| split per module as those modules land (see docs/api/versioning.md); this
| file only wires the version group and the platform-level endpoints that
| exist before any gameplay does.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    Route::get('/health', HealthController::class)->name('health');

});
