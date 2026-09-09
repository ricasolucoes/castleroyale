<!doctype html>
<html lang="{{ str_replace('_', '-', $locale ?? app()->getLocale()) }}" data-theme="dark">
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
        <meta property="og:type" content="website">
        <meta property="og:title" content="@yield('title') — {{ config('game.name') }}">
        <meta property="og:description" content="@yield('description')">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ asset('images/hero-castle.webp') }}">
        <meta property="og:site_name" content="{{ config('game.name') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="@yield('title') — {{ config('game.name') }}">
        <meta name="twitter:description" content="@yield('description')">
        <meta name="twitter:image" content="{{ asset('images/hero-castle.webp') }}">
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        @yield('head')
        <script>
            (function() {
                try {
                    var t = localStorage.getItem('cr_institutional_theme');
                    if (t) document.documentElement.setAttribute('data-theme', t);
                } catch(e) {}
            })();
        </script>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="site-body">
        <a class="site-skip-link" href="#main-content">{{ __('institutional.common.skip') }}</a>
        <header class="site-header">
            <div class="site-container site-header-inner">
                <a class="site-mark" href="{{ route('site.home', ['locale' => $locale]) }}" aria-label="{{ __('institutional.common.home') }}">
                    <span class="site-mark-crest" aria-hidden="true">✦</span>
                    <span class="site-mark-title">{{ config('game.name') }}</span>
                    <span class="site-mark-tag">MMORTS</span>
                </a>
                <nav class="site-nav" aria-label="{{ __('institutional.nav.game') }}">
                    <a href="{{ route('site.features', ['locale' => $locale]) }}" @if (request()->routeIs('site.features')) aria-current="page" @endif>{{ __('institutional.nav.game') }}</a>
                    <a href="{{ route('site.home', ['locale' => $locale]) }}#pillars">{{ __('institutional.home.pillars_title') }}</a>
                    <a href="{{ route('site.support', ['locale' => $locale]) }}" @if (request()->routeIs('site.support')) aria-current="page" @endif>{{ __('institutional.nav.support') }}</a>
                    <a href="{{ route('site.privacy', ['locale' => $locale]) }}" @if (request()->routeIs('site.privacy', 'site.terms')) aria-current="page" @endif>{{ __('institutional.nav.legal') }}</a>
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
                    <button type="button" class="site-theme-btn" aria-label="{{ __('institutional.nav.theme_toggle') }}" title="{{ __('institutional.nav.theme_toggle') }}">
                        ✦
                    </button>
                </div>
            </div>
        </header>

        <main id="main-content" class="site-main">
            @yield('content')
        </main>

        <footer class="site-footer">
            <div class="site-container site-footer-inner">
                <div class="site-footer-brand">
                    <span class="site-mark-crest" aria-hidden="true">✦</span>
                    <div>
                        <p class="site-footer-note">&copy; {{ date('Y') }} {{ config('game.name') }}. {{ __('institutional.common.all_rights_reserved') }}</p>
                    </div>
                </div>
                <div class="site-footer-status">
                    <span class="site-beacon-dot" aria-hidden="true"></span>
                    <span>{{ __('institutional.common.server_status') }}</span>
                </div>
                <nav class="site-footer-nav" aria-label="{{ __('institutional.nav.legal') }}">
                    <a href="{{ route('site.privacy', ['locale' => $locale]) }}">{{ __('institutional.nav.privacy') }}</a>
                    <a href="{{ route('site.terms', ['locale' => $locale]) }}">{{ __('institutional.nav.terms') }}</a>
                    <a href="{{ route('site.support', ['locale' => $locale]) }}">{{ __('institutional.nav.support') }}</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
