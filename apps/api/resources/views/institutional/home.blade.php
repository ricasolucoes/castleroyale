@extends('layouts.institutional')

@section('title', __('institutional.home.title'))
@section('description', __('institutional.meta.home.description'))

@section('content')
    <div class="site-hero-wrap">
        <div class="site-hero-bg" aria-hidden="true"></div>
        <div class="site-hero-overlay" aria-hidden="true"></div>
        <section class="site-hero site-container" aria-labelledby="home-title">
            <div class="site-hero-copy">
                <div class="site-eyebrow-badge">
                    <span aria-hidden="true">✦</span>
                    <span>{{ __('institutional.home.badge') }}</span>
                </div>
                <h1 id="home-title" class="site-display">
                    {{ __('institutional.home.title') }}
                </h1>
                <p class="site-lead">
                    {{ __('institutional.home.intro') }}
                </p>
                <div class="site-actions">
                    <a class="site-button site-button-primary" href="{{ route('site.features', ['locale' => $locale]) }}">
                        <span>{{ __('institutional.home.cta') }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                    <a class="site-button site-button-secondary" href="{{ route('site.support', ['locale' => $locale]) }}">
                        {{ __('institutional.home.support_cta') }}
                    </a>
                </div>
                <div class="site-hero-badges" aria-label="{{ __('institutional.common.mobile_only') }}">
                    <span class="site-hero-badge-item"><span class="bullet" aria-hidden="true">✦</span> {{ __('institutional.common.app_store') }}</span>
                    <span class="site-hero-badge-item"><span class="bullet" aria-hidden="true">✦</span> {{ __('institutional.common.google_play') }}</span>
                    <span class="site-hero-badge-item"><span class="bullet" aria-hidden="true">✦</span> {{ __('institutional.common.server_status') }}</span>
                </div>
            </div>
            <div class="site-hero-hud" aria-hidden="true">
                <div class="site-hud-header">
                    <span class="site-hud-status">
                        <span class="site-beacon-dot"></span>
                        <span>{{ config('game.name') }}</span>
                    </span>
                    <span class="site-mark-tag">ONLINE</span>
                </div>
                <div class="site-hud-media">
                    <img src="{{ asset('images/hero-castle.webp') }}" alt="{{ config('game.name') }}" loading="eager">
                    <div class="site-hud-media-overlay"></div>
                </div>
                <div class="site-hud-body">
                    <h2 class="site-hud-title">{{ __('institutional.home.pillars.0.title') }}</h2>
                    <p class="site-hud-caption">{{ __('institutional.home.pillars.0.body') }}</p>
                    <div class="site-hud-resources">
                        <div class="site-hud-res-item">
                            <span class="site-hud-res-dot food"></span>
                            <span class="site-hud-res-name">Food</span>
                        </div>
                        <div class="site-hud-res-item">
                            <span class="site-hud-res-dot wood"></span>
                            <span class="site-hud-res-name">Wood</span>
                        </div>
                        <div class="site-hud-res-item">
                            <span class="site-hud-res-dot stone"></span>
                            <span class="site-hud-res-name">Stone</span>
                        </div>
                        <div class="site-hud-res-item">
                            <span class="site-hud-res-dot iron"></span>
                            <span class="site-hud-res-name">Iron</span>
                        </div>
                        <div class="site-hud-res-item">
                            <span class="site-hud-res-dot gold"></span>
                            <span class="site-hud-res-name">Gold</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="site-stats-strip">
        <div class="site-container site-stats-grid">
            @foreach (__('institutional.home.stats') as $stat)
                <div class="site-stat-card">
                    <span class="site-stat-value">{{ $stat['value'] }}</span>
                    <span class="site-stat-label">{{ $stat['label'] }}</span>
                    <p class="site-stat-desc">{{ $stat['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <section id="pillars" class="site-section site-container" aria-labelledby="pillars-title">
        <div class="site-section-head">
            <p class="site-eyebrow">{{ __('institutional.home.eyebrow') }}</p>
            <h2 id="pillars-title" class="site-title">{{ __('institutional.home.pillars_title') }}</h2>
            <p class="site-lead">{{ __('institutional.home.pillars_intro') }}</p>
        </div>
        <div class="site-card-grid">
            @foreach (__('institutional.home.pillars') as $pillar)
                <article class="site-pillar-card">
                    <div class="site-pillar-media">
                        <img src="{{ asset($pillar['image'] ?? 'images/hero-castle.webp') }}" alt="{{ $pillar['title'] }}" loading="lazy">
                        <div class="site-pillar-media-gradient"></div>
                    </div>
                    <div class="site-pillar-content">
                        <span class="site-pillar-tag" aria-hidden="true">{{ $pillar['tag'] ?? str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 class="site-heading">{{ $pillar['title'] }}</h3>
                        <p class="site-lead">{{ $pillar['body'] }}</p>
                        @if (!empty($pillar['highlights']))
                            <div class="site-pillar-bullets">
                                @foreach ($pillar['highlights'] as $hl)
                                    <div class="site-bullet-item">
                                        <span class="site-bullet-icon" aria-hidden="true">✓</span>
                                        <span>{{ $hl }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section id="chronicles" class="site-section site-container" aria-labelledby="chronicles-title">
        <div class="site-section-head">
            <p class="site-eyebrow">{{ __('institutional.home.chronicles_eyebrow') }}</p>
            <h2 id="chronicles-title" class="site-title">{{ __('institutional.home.chronicles_title') }}</h2>
            <p class="site-lead">{{ __('institutional.home.chronicles_intro') }}</p>
        </div>
        <div class="site-card-grid site-chronicles-grid">
            @foreach (__('institutional.home.chronicles') as $ch)
                <article class="site-pillar-card site-chronicle-card">
                    <div class="site-pillar-media">
                        <img src="{{ asset($ch['image']) }}" alt="{{ $ch['title'] }}" loading="lazy">
                        <div class="site-pillar-media-gradient"></div>
                    </div>
                    <div class="site-pillar-content">
                        <div class="site-chronicle-header">
                            <span class="site-pillar-tag" aria-hidden="true">{{ $ch['tag'] }}</span>
                            <span class="site-chronicle-badge">{{ $ch['chapter'] }}</span>
                        </div>
                        <h3 class="site-heading">{{ $ch['title'] }}</h3>
                        <p class="site-lead">{{ $ch['body'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="site-section site-forge site-container" aria-labelledby="forge-title">
        <div class="site-forge-card">
            <div class="site-forge-media">
                <img src="{{ asset('images/sword.webp') }}" alt="{{ __('institutional.home.forge_title') }}" loading="lazy">
            </div>
            <div class="site-forge-copy">
                <p class="site-eyebrow">{{ __('institutional.home.forge_eyebrow') }}</p>
                <h2 id="forge-title" class="site-title">{{ __('institutional.home.forge_title') }}</h2>
                <p class="site-lead">{{ __('institutional.home.forge_body') }}</p>
                <div class="site-actions">
                    <a class="site-button site-button-primary" href="{{ route('site.features', ['locale' => $locale]) }}">
                        {{ __('institutional.common.view_features') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="site-section site-trust" aria-labelledby="trust-title">
        <div class="site-container site-trust-inner">
            <div class="site-section-head">
                <p class="site-eyebrow">{{ __('institutional.home.trust_title') }}</p>
                <h2 id="trust-title" class="site-title">{{ __('institutional.home.trust_title') }}</h2>
                <p class="site-lead">{{ __('institutional.home.trust_body') }}</p>
            </div>
            <div class="site-trust-points">
                @foreach (__('institutional.home.trust_points') as $pt)
                    <div class="site-trust-point">
                        <span class="site-trust-point-icon" aria-hidden="true">🛡</span>
                        <h3 class="site-heading">{{ $pt['title'] }}</h3>
                        <p class="site-lead">{{ $pt['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="mobile" class="site-section site-container" aria-labelledby="store-title">
        <div class="site-store-card">
            <p class="site-eyebrow">{{ __('institutional.common.mobile_only') }}</p>
            <h2 id="store-title" class="site-title">{{ __('institutional.home.store_title') }}</h2>
            <p class="site-lead">{{ __('institutional.home.store_body') }}</p>
            <div class="site-store-buttons">
                <div class="site-store-pill">
                    <span class="icon" aria-hidden="true"></span>
                    <span>{{ __('institutional.common.app_store') }}</span>
                </div>
                <div class="site-store-pill">
                    <span class="icon" aria-hidden="true">▶</span>
                    <span>{{ __('institutional.common.google_play') }}</span>
                </div>
            </div>
            <p class="site-field-hint">{{ __('institutional.home.store_status') }}</p>
        </div>
    </section>
@endsection
