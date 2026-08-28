<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('publishes unique SEO metadata for every page and locale', function (): void {
    $titles = [];
    $descriptions = [];

    foreach (['pt-BR', 'en', 'es'] as $locale) {
        foreach (['site.home', 'site.features', 'site.support', 'site.privacy', 'site.terms'] as $routeName) {
            $response = $this->get(route($routeName, ['locale' => $locale]));
            $html = $response->getContent();

            preg_match('/<title>([^<]+)<\/title>/', $html, $titleMatch);
            preg_match('/<meta name="description" content="([^"]+)">/', $html, $descriptionMatch);

            expect($titleMatch[1] ?? '')->not->toBeEmpty();
            expect($descriptionMatch[1] ?? '')->not->toBeEmpty();
            expect($html)->toContain('rel="canonical"');
            expect($html)->toContain('hreflang="pt-BR"');
            expect($html)->toContain('hreflang="en"');
            expect($html)->toContain('hreflang="es"');

            $titles[] = $titleMatch[1];
            $descriptions[] = $descriptionMatch[1];
        }
    }

    expect(count(array_unique($titles)))->toBe(count($titles));
    expect(count(array_unique($descriptions)))->toBe(count($descriptions));
});

it('publishes only public institutional URLs in the sitemap', function (): void {
    $response = $this->get(route('site.sitemap'));

    $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $response->assertSee('<urlset', false);
    $response->assertSee(route('site.home', ['locale' => 'en']), false);
    $response->assertSee(route('site.terms', ['locale' => 'es']), false);
    $response->assertDontSee('/admin', false);
    $response->assertDontSee('/api', false);
});

it('protects crawler access to staff and API surfaces', function (): void {
    $response = $this->get(route('site.robots'));

    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $response->assertSee('Disallow: /admin');
    $response->assertSee('Disallow: /api');
    $response->assertSee('Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml');
});

it('keeps legal pages versioned and free of player or account state', function (): void {
    foreach (['site.privacy', 'site.terms'] as $routeName) {
        $response = $this->get(route($routeName, ['locale' => 'pt-BR']));

        $response->assertOk()->assertSee(config('institutional.content_updated_at'));
        $response->assertDontSee('account_id')->assertDontSee('world_id')->assertDontSee('player_id');
    }
});

it('keeps institutional views free of literal visual tokens', function (): void {
    $contents = collect([
        ...File::allFiles(resource_path('views/institutional')),
        ...File::allFiles(resource_path('views/layouts')),
    ])->map(static fn (SplFileInfo $file): string => $file->getContents())->implode("\n");

    expect($contents)->not->toMatch('/#[0-9A-Fa-f]{3,8}/');
    expect($contents)->not->toContain('font-size:');
    expect($contents)->not->toMatch('/\b\d+px\b/');
    expect($contents)->not->toContain('Project Dominion');
});
