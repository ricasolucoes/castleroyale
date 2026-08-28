@extends('layouts.institutional')

@section('title', __('institutional.home.title'))

@section('content')
    <section class="site-hero site-container" aria-labelledby="home-title">
        <div class="site-hero-copy">
            <p class="site-eyebrow">{{ __('institutional.home.eyebrow') }}</p>
            <h1 id="home-title" class="site-display">{{ __('institutional.home.title') }}</h1>
            <p class="site-lead">{{ __('institutional.home.intro') }}</p>
            <div class="site-actions">
                <a class="site-button site-button-primary" href="{{ route('site.features', ['locale' => $locale]) }}">{{ __('institutional.home.cta') }}</a>
                <a class="site-button site-button-secondary" href="{{ route('site.support', ['locale' => $locale]) }}">{{ __('institutional.home.support_cta') }}</a>
            </div>
        </div>
        <div class="site-hero-art" aria-hidden="true">
            <span class="site-art-sun"></span>
            <span class="site-art-horizon"></span>
            <span class="site-art-tower"></span>
        </div>
    </section>

    <section class="site-section site-container" aria-labelledby="pillars-title">
        <p class="site-eyebrow">{{ __('institutional.home.eyebrow') }}</p>
        <h2 id="pillars-title" class="site-title">{{ __('institutional.home.pillars_title') }}</h2>
        <div class="site-card-grid">
            @foreach (__('institutional.home.pillars') as $pillar)
                <article class="site-card">
                    <span class="site-card-index" aria-hidden="true">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="site-heading">{{ $pillar['title'] }}</h3>
                    <p>{{ $pillar['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="site-section site-trust" aria-labelledby="trust-title">
        <div class="site-container site-trust-inner">
            <div>
                <p class="site-eyebrow">{{ __('institutional.home.trust_title') }}</p>
                <h2 id="trust-title" class="site-title">{{ __('institutional.home.trust_title') }}</h2>
            </div>
            <p class="site-lead">{{ __('institutional.home.trust_body') }}</p>
        </div>
    </section>

    <section id="mobile" class="site-section site-container site-store" aria-labelledby="store-title">
        <p class="site-eyebrow">{{ __('institutional.common.mobile_only') }}</p>
        <h2 id="store-title" class="site-title">{{ __('institutional.home.store_title') }}</h2>
        <p class="site-lead">{{ __('institutional.home.store_body') }}</p>
    </section>
@endsection
