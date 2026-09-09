@extends('layouts.institutional')

@section('title', __('institutional.features.title'))
@section('description', __('institutional.meta.features.description'))

@section('content')
    <section class="site-page-intro site-container" aria-labelledby="features-title">
        <p class="site-eyebrow">{{ __('institutional.features.eyebrow') }}</p>
        <h1 id="features-title" class="site-display">{{ __('institutional.features.title') }}</h1>
        <p class="site-lead">{{ __('institutional.features.intro') }}</p>
    </section>

    <section class="site-section site-container" aria-label="{{ __('institutional.features.eyebrow') }}">
        <div class="site-feature-list">
            @foreach (__('institutional.features.sections') as $section)
                <article class="site-feature-row">
                    <div class="site-feature-media">
                        <img src="{{ asset($section['image'] ?? 'images/village.webp') }}" alt="{{ $section['title'] }}" loading="lazy">
                    </div>
                    <div class="site-feature-copy">
                        <p class="site-eyebrow">{{ __('institutional.features.eyebrow') }} • {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</p>
                        <h2 class="site-title">{{ $section['title'] }}</h2>
                        <p class="site-lead">{{ $section['body'] }}</p>
                        @if (!empty($section['details']))
                            <p class="site-lead">{{ $section['details'] }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
