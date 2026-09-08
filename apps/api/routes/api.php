<?php

declare(strict_types=1);

use App\Http\Middleware\CheckDeviceSession;
use Game\Alliance\Interface\Http\AllianceController;
use Game\Alliance\Interface\Http\CreateAllianceController;
use Game\City\Interface\Http\CityController;
use Game\Construction\Interface\Http\BuildingUpgradeController;
use Game\Identity\Interface\Http\AuthController;
use Game\Military\Interface\Http\MilitaryController;
use Game\Platform\Interface\Http\HealthController;
use Game\Player\Interface\Http\GameBootstrapController;
use Game\Technology\Interface\Http\ResearchController;
use Game\Technology\Interface\Http\TechnologyTreeController;
use Game\World\Interface\Http\WorldController;
use Game\World\Interface\Http\WorldSelectionController;
use Game\World\Interface\Http\WorldViewportController;
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

    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function (): void {
        Route::post('/guest', [AuthController::class, 'guest'])->name('guest');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::post('/social', [AuthController::class, 'social'])->name('social');
    });

    Route::middleware(['auth:sanctum', CheckDeviceSession::class])->group(function (): void {
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/sessions', [AuthController::class, 'sessions'])->name('sessions');
            Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession'])->name('sessions.revoke');
            Route::post('/upgrade', [AuthController::class, 'upgrade'])->name('upgrade');
        });

        Route::post('/game/bootstrap', GameBootstrapController::class)->name('game.bootstrap');
        Route::get('/game/worlds', WorldSelectionController::class)->name('game.worlds');
        Route::post('/game/worlds/{worldId}/select', [WorldSelectionController::class, 'select'])
            ->name('game.worlds.select');
        Route::get('/game/city', CityController::class)->name('game.city');
        Route::get('/game/city/{cityId}', [CityController::class, 'show'])->name('game.city.show');
        Route::get('/game/world', WorldController::class)->name('game.world');
        Route::get('/game/world/viewport', WorldViewportController::class)->name('game.world.viewport');
        Route::get('/game/military', MilitaryController::class)->name('game.military');
        Route::get('/game/alliance', AllianceController::class)->name('game.alliance');
        Route::post('/game/alliance', CreateAllianceController::class)->name('game.alliance.create');
        Route::post('/game/city/buildings/{code}/upgrade', BuildingUpgradeController::class)
            ->name('game.city.buildings.upgrade');
        Route::get('/game/technologies', TechnologyTreeController::class)->name('game.technologies');
        Route::post('/game/technologies/{code}/research', ResearchController::class)
            ->name('game.technologies.research');
    });

});
