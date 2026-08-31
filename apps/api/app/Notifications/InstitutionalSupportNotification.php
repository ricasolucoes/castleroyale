<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InstitutionalSupportNotification extends Notification
{
    use Queueable;

    public readonly string $supportLocale;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        string $locale,
        public readonly string $subjectLine,
        public readonly string $messageBody,
    ) {
        $this->supportLocale = $locale;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('['.config('game.name').'] '.$this->subjectLine)
            ->replyTo($this->email, $this->name)
            ->view('emails.institutional-support', [
                'name' => $this->name,
                'email' => $this->email,
                'locale' => $this->supportLocale,
                'subjectLine' => $this->subjectLine,
                'messageBody' => $this->messageBody,
            ]);
    }
}
