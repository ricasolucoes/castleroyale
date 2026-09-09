@extends('layouts.institutional')

@section('title', __('institutional.privacy.title'))
@section('description', __('institutional.meta.privacy.description'))

@section('content')
    <section class="site-page-intro site-container" aria-labelledby="privacy-title">
        <div class="site-legal-wrap">
            <p class="site-eyebrow">{{ __('institutional.privacy.eyebrow') }}</p>
            <h1 id="privacy-title" class="site-display">{{ __('institutional.privacy.title') }}</h1>
            <p class="site-field-hint">{{ __('institutional.privacy.updated') }}: {{ config('institutional.content_updated_at') }}</p>
        </div>
    </section>
    <section class="site-section site-container" aria-label="{{ __('institutional.privacy.title') }}">
        <div class="site-legal-wrap site-legal">
            @foreach (__('institutional.privacy.sections') as $section)
                <article class="site-legal-card">
                    <h2 class="site-title">{{ $section['title'] }}</h2>
                    <p class="site-lead">{{ $section['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection
