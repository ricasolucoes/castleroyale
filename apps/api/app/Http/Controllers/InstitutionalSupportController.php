<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InstitutionalSupportRequest;
use App\Notifications\InstitutionalSupportNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

final class InstitutionalSupportController extends Controller
{
    public function store(InstitutionalSupportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $correlationId = (string) Str::ulid();

        try {
            Notification::route('mail', (string) config('game.support_email'))->notify(
                new InstitutionalSupportNotification(
                    name: $data['name'],
                    email: $data['email'],
                    locale: $data['locale'],
                    subjectLine: $this->redactSensitiveContent($data['subject']),
                    messageBody: $this->redactSensitiveContent($data['message']),
                ),
            );
        } catch (Throwable $exception) {
            Log::warning('institutional_support_submission_failed', [
                'locale' => $data['locale'],
                'outcome' => 'mail_failure',
                'correlation_id' => $correlationId,
                'exception' => $exception::class,
            ]);

            return redirect()
                ->route('site.support', ['locale' => $data['locale']])
                ->withInput()
                ->with('support_status', 'failure');
        }

        Log::info('institutional_support_submission_succeeded', [
            'locale' => $data['locale'],
            'outcome' => 'accepted',
            'correlation_id' => $correlationId,
        ]);

        return redirect()
            ->route('site.support', ['locale' => $data['locale']])
            ->with('support_status', 'success');
    }

    private function redactSensitiveContent(string $content): string
    {
        return preg_replace(
            '/\b(password|passcode|token|access[_ -]?token|authorization|account[_ -]?id|world[_ -]?id)\b\s*[:=]\s*[^\s,;]+/iu',
            '$1: [redacted]',
            $content,
        ) ?? '[redacted]';
    }
}
