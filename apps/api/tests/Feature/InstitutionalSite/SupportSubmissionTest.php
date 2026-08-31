<?php

declare(strict_types=1);

use App\Notifications\InstitutionalSupportNotification;
use Illuminate\Support\Facades\Notification;

function institutionalSupportPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Supporter',
        'email' => 'supporter@example.test',
        'locale' => 'en',
        'subject' => 'A question',
        'message' => 'Please help with the public site.',
    ], $overrides);
}

beforeEach(fn (): object => Notification::fake());

it('delivers valid support submissions and returns localized success state', function (): void {
    $response = $this->from(route('site.support', ['locale' => 'en']))
        ->post(route('site.support.submit'), institutionalSupportPayload());

    $response->assertRedirect(route('site.support', ['locale' => 'en']))
        ->assertSessionHas('support_status', 'success');
    Notification::assertSentOnDemand(InstitutionalSupportNotification::class, function (InstitutionalSupportNotification $notification, array $channels, object $notifiable): bool {
        return $notification->subjectLine === 'A question'
            && $notification->messageBody === 'Please help with the public site.'
            && $notifiable->routes['mail'] === 'support@example.test';
    });
});

it('rejects invalid and unexpected support fields without sending mail', function (array $overrides, string $field): void {
    $response = $this->post(route('site.support.submit'), institutionalSupportPayload($overrides));

    $response->assertSessionHasErrors($field);
    Notification::assertNothingSent();
})->with([
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'oversized message' => [['message' => str_repeat('x', 5001)], 'message'],
    'unknown field' => [['account_id' => 'forbidden'], 'form'],
]);

it('rate limits repeated submissions by normalized email and client IP', function (): void {
    foreach (range(1, 5) as $_) {
        $this->post(route('site.support.submit'), institutionalSupportPayload([
            'email' => 'Supporter@Example.Test',
        ]));
    }

    $response = $this->post(route('site.support.submit'), institutionalSupportPayload());

    $response->assertTooManyRequests();
    Notification::assertSentOnDemand(InstitutionalSupportNotification::class, 5);
});

it('redacts credentials and player identifiers from the notification payload', function (): void {
    $this->post(route('site.support.submit'), institutionalSupportPayload([
        'subject' => 'password=secret',
        'message' => 'token=abc account_id=123 world_id=456',
    ]))->assertSessionHas('support_status', 'success');

    Notification::assertSentOnDemand(InstitutionalSupportNotification::class, function (InstitutionalSupportNotification $notification): bool {
        return ! str_contains($notification->subjectLine, 'secret')
            && ! str_contains($notification->messageBody, 'abc')
            && ! str_contains($notification->messageBody, '123')
            && ! str_contains($notification->messageBody, '456');
    });
});
