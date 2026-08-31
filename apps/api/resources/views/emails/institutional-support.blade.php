<p>{{ __('institutional.support.mail.intro', [], $locale) }}</p>
<p><strong>{{ __('institutional.support.mail.name', [], $locale) }}:</strong> {{ $name }}</p>
<p><strong>{{ __('institutional.support.mail.email', [], $locale) }}:</strong> {{ $email }}</p>
<p><strong>{{ __('institutional.support.mail.subject', [], $locale) }}:</strong> {{ $subjectLine }}</p>
<p><strong>{{ __('institutional.support.mail.message', [], $locale) }}:</strong></p>
<p>{!! nl2br(e($messageBody)) !!}</p>
