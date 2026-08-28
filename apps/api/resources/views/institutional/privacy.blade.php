@extends('layouts.institutional')

@section('title', __('institutional.privacy.title'))

@section('content')
    <section class="site-page-intro site-container" aria-labelledby="privacy-title">
        <p class="site-eyebrow">{{ __('institutional.privacy.eyebrow') }}</p>
        <h1 id="privacy-title" class="site-display">{{ __('institutional.privacy.title') }}</h1>
    </section>
    <section class="site-section site-container site-legal" aria-label="{{ __('institutional.privacy.title') }}">
        @foreach (__('institutional.privacy.sections') as $section)
            <article>
                <h2 class="site-title">{{ $section['title'] }}</h2>
                <p class="site-lead">{{ $section['body'] }}</p>
            </article>
        @endforeach
    </section>
@endsection
