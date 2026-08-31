<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class InstitutionalSiteController extends Controller
{
    public function home(): View
    {
        return $this->page('home', 'site.home');
    }

    public function features(): View
    {
        return $this->page('features', 'site.features');
    }

    public function support(): View
    {
        return $this->page('support', 'site.support');
    }

    public function privacy(): View
    {
        return $this->page('privacy', 'site.privacy');
    }

    public function terms(): View
    {
        return $this->page('terms', 'site.terms');
    }

    public function sitemap(): Response
    {
        $urls = [];

        foreach (['site.home', 'site.features', 'site.support', 'site.privacy', 'site.terms'] as $routeName) {
            foreach (['pt-BR', 'en', 'es'] as $locale) {
                $urls[] = '<url><loc>'.e($this->publicUrl($routeName, $locale)).'</loc></url>';
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', $urls)
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $sitemapUrl = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        return response("User-agent: *\nDisallow: /admin\nDisallow: /api\nSitemap: {$sitemapUrl}\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function page(string $view, string $routeName): View
    {
        $locales = ['pt-BR', 'en', 'es'];
        $alternateUrls = [];
        $template = match ($view) {
            'home' => 'institutional.home',
            'features' => 'institutional.features',
            'support' => 'institutional.support',
            'privacy' => 'institutional.privacy',
            'terms' => 'institutional.terms',
            default => throw new InvalidArgumentException('Unknown institutional page.'),
        };

        foreach ($locales as $locale) {
            $alternateUrls[$locale] = $this->publicUrl($routeName, $locale);
        }

        return view($template, [
            'locale' => app()->getLocale(),
            'canonicalUrl' => $this->publicUrl($routeName, app()->getLocale()),
            'alternateUrls' => $alternateUrls,
            'routeName' => $routeName,
        ]);
    }

    private function publicUrl(string $routeName, string $locale): string
    {
        $parsedPath = parse_url(route($routeName, ['locale' => $locale]), PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : '/';
        $path = $path === '/' ? '' : $path;

        return rtrim((string) config('app.url'), '/').$path.'?locale='.rawurlencode($locale);
    }
}
