<!doctype html>
<html lang="{{ str_replace('_', '-', $locale ?? app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') — {{ config('game.name') }}</title>
        <meta name="description" content="@yield('description')">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        @foreach (['pt-BR', 'en', 'es'] as $alternateLocale)
            <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $alternateUrls[$alternateLocale] }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['pt-BR'] }}">
        @yield('head')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="site-body">
        <a class="site-skip-link" href="#main-content">{{ __('institutional.common.skip') }}</a>
        <header class="site-header">
            <div class="site-container site-header-inner">
                <a class="site-mark" href="{{ route('site.home', ['locale' => $locale]) }}" aria-label="{{ __('institutional.common.home') }}">
                    <span class="site-mark-symbol" aria-hidden="true">✦</span>
                    <span>{{ config('game.name') }}</span>
                </a>
                <nav class="site-nav" aria-label="{{ __('institutional.nav.game') }}">
                    <a href="{{ route('site.features', ['locale' => $locale]) }}">{{ __('institutional.nav.game') }}</a>
                    <a href="{{ route('site.support', ['locale' => $locale]) }}">{{ __('institutional.nav.support') }}</a>
                    <a href="{{ route('site.privacy', ['locale' => $locale]) }}">{{ __('institutional.nav.legal') }}</a>
                </nav>
                <div class="site-header-actions">
                    <a class="site-button site-button-primary site-button-compact" href="{{ route('site.home', ['locale' => $locale]) }}#mobile">
                        {{ __('institutional.nav.play') }}
                    </a>
                    <div class="site-locale" aria-label="{{ __('institutional.nav.locale') }}">
                        <span class="site-visually-hidden">{{ __('institutional.common.language') }}</span>
                        @foreach (['pt-BR', 'en', 'es'] as $supportedLocale)
                            <a href="{{ route(request()->route()?->getName() ?? 'site.home', ['locale' => $supportedLocale]) }}"
                                @if ($supportedLocale === $locale) aria-current="true" @endif>
                                {{ strtoupper($supportedLocale === 'pt-BR' ? 'pt' : $supportedLocale) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="site-main">
            @yield('content')
        </main>

        <footer class="site-footer">
            <div class="site-container site-footer-inner">
                <p class="site-footer-note">{{ __('institutional.common.mobile_only') }}</p>
                <nav class="site-footer-nav" aria-label="{{ __('institutional.nav.legal') }}">
                    <a href="{{ route('site.privacy', ['locale' => $locale]) }}">{{ __('institutional.nav.privacy') }}</a>
                    <a href="{{ route('site.terms', ['locale' => $locale]) }}">{{ __('institutional.nav.terms') }}</a>
                    <a href="{{ route('site.support', ['locale' => $locale]) }}">{{ __('institutional.nav.support') }}</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
