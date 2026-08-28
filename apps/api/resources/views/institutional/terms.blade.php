@extends('layouts.institutional')

@section('title', __('institutional.terms.title'))

@section('content')
    <section class="site-page-intro site-container" aria-labelledby="terms-title">
        <p class="site-eyebrow">{{ __('institutional.terms.eyebrow') }}</p>
        <h1 id="terms-title" class="site-display">{{ __('institutional.terms.title') }}</h1>
    </section>
    <section class="site-section site-container site-legal" aria-label="{{ __('institutional.terms.title') }}">
        @foreach (__('institutional.terms.sections') as $section)
            <article>
                <h2 class="site-title">{{ $section['title'] }}</h2>
                <p class="site-lead">{{ $section['body'] }}</p>
            </article>
        @endforeach
    </section>
@endsection
