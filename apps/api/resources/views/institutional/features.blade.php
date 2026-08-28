@extends('layouts.institutional')

@section('title', __('institutional.features.title'))

@section('content')
    <section class="site-page-intro site-container" aria-labelledby="features-title">
        <p class="site-eyebrow">{{ __('institutional.features.eyebrow') }}</p>
        <h1 id="features-title" class="site-display">{{ __('institutional.features.title') }}</h1>
        <p class="site-lead">{{ __('institutional.features.intro') }}</p>
    </section>
    <section class="site-section site-container" aria-label="{{ __('institutional.features.eyebrow') }}">
        <div class="site-feature-list">
            @foreach (__('institutional.features.sections') as $section)
                <article class="site-feature">
                    <span class="site-feature-marker" aria-hidden="true"></span>
                    <div>
                        <h2 class="site-title">{{ $section['title'] }}</h2>
                        <p class="site-lead">{{ $section['body'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
