@extends('layouts.institutional')

@section('title', __('institutional.support.title'))

@section('content')
    <section class="site-page-intro site-container" aria-labelledby="support-title">
        <p class="site-eyebrow">{{ __('institutional.support.eyebrow') }}</p>
        <h1 id="support-title" class="site-display">{{ __('institutional.support.title') }}</h1>
        <p class="site-lead">{{ __('institutional.support.intro') }}</p>
    </section>
    <section class="site-section site-container site-support-grid" aria-label="{{ __('institutional.support.title') }}">
        <form class="site-form site-card" method="post" action="{{ route('site.support') }}">
            @csrf
            <input type="hidden" name="locale" value="{{ $locale }}">
            <div class="site-field">
                <label for="support-name">{{ __('institutional.support.name') }} <span aria-hidden="true">*</span></label>
                <input id="support-name" name="name" value="{{ old('name') }}" autocomplete="name" required aria-describedby="support-name-hint">
                <span id="support-name-hint" class="site-field-hint">{{ __('institutional.support.required') }}</span>
                @error('name') <p class="site-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="site-field">
                <label for="support-email">{{ __('institutional.support.email') }} <span aria-hidden="true">*</span></label>
                <input id="support-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email') <p class="site-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="site-field">
                <label for="support-subject">{{ __('institutional.support.subject') }} <span aria-hidden="true">*</span></label>
                <input id="support-subject" name="subject" value="{{ old('subject') }}" required>
                @error('subject') <p class="site-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="site-field">
                <label for="support-message">{{ __('institutional.support.message') }} <span aria-hidden="true">*</span></label>
                <textarea id="support-message" name="message" rows="6" required>{{ old('message') }}</textarea>
                @error('message') <p class="site-field-error">{{ $message }}</p> @enderror
            </div>
            <button class="site-button site-button-primary" type="submit">{{ __('institutional.support.submit') }}</button>
        </form>
        <aside class="site-card site-support-aside" aria-labelledby="alternate-title">
            <h2 id="alternate-title" class="site-heading">{{ __('institutional.support.alternate') }}</h2>
            <p>{{ __('institutional.support.alternate_body') }}</p>
            <a class="site-link" href="mailto:{{ config('game.support_email') }}">{{ config('game.support_email') }}</a>
        </aside>
    </section>
@endsection
