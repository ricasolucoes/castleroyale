<?php

declare(strict_types=1);

use App\Http\Controllers\InstitutionalSiteController;
use App\Http\Middleware\SetInstitutionalLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(SetInstitutionalLocale::class)->group(function (): void {
    Route::get('/', [InstitutionalSiteController::class, 'home'])->name('site.home');
    Route::get('/features', [InstitutionalSiteController::class, 'features'])->name('site.features');
    Route::get('/support', [InstitutionalSiteController::class, 'support'])->name('site.support');
    Route::get('/privacy', [InstitutionalSiteController::class, 'privacy'])->name('site.privacy');
    Route::get('/terms', [InstitutionalSiteController::class, 'terms'])->name('site.terms');
});
