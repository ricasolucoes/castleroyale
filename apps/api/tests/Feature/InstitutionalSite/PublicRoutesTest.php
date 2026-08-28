<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;

it('renders every public page in every supported locale', function (): void {
    $routes = [
        'site.home',
        'site.features',
        'site.support',
        'site.privacy',
        'site.terms',
    ];

    foreach (['pt-BR', 'en', 'es'] as $locale) {
        foreach ($routes as $route) {
            /** @var TestResponse $response */
            $response = $this->get(route($route, ['locale' => $locale]));

            $response->assertOk();
            $response->assertSee('<header', false);
            $response->assertSee('<nav', false);
            $response->assertSee('<main', false);
            $response->assertSee('<footer', false);
            $response->assertSee('lang="'.str_replace('_', '-', $locale).'"', false);
        }
    }
});

it('replaces the stock welcome page at the root route', function (): void {
    $this->get(route('site.home'))
        ->assertOk()
        ->assertDontSee('Laravel logo')
        ->assertSee(config('game.name'));
});
