@extends('layouts.institutional')

@section('title', __('institutional.terms.title'))
@section('description', __('institutional.meta.terms.description'))

@section('content')
    <section class="site-page-intro site-container" aria-labelledby="terms-title">
        <div class="site-legal-wrap">
            <p class="site-eyebrow">{{ __('institutional.terms.eyebrow') }}</p>
            <h1 id="terms-title" class="site-display">{{ __('institutional.terms.title') }}</h1>
            <p class="site-field-hint">{{ __('institutional.terms.updated') }}: {{ config('institutional.content_updated_at') }}</p>
        </div>
    </section>
    <section class="site-section site-container" aria-label="{{ __('institutional.terms.title') }}">
        <div class="site-legal-wrap site-legal">
            @foreach (__('institutional.terms.sections') as $section)
                <article class="site-legal-card">
                    <h2 class="site-title">{{ $section['title'] }}</h2>
                    <p class="site-lead">{{ $section['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection
