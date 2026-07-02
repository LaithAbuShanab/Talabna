<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends the raw password reset token to the user instead of Laravel's
 * default web-URL reset link, since this backend is API/mobile-only and
 * has no reset-password web page for the link to point to. The client
 * app collects this token and submits it back to POST /api/v1/auth/reset-password
 * along with the new password. Not an OTP: this is Laravel's standard
 * long random token, just delivered without a URL wrapper.
 */
final class ApiResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(trans('passwords.reset_subject'))
            ->line(trans('passwords.reset_intro'))
            ->line(trans('passwords.reset_token', ['token' => $this->token]))
            ->line(trans('passwords.reset_expire', ['count' => (string) config('auth.passwords.users.expire')]))
            ->line(trans('passwords.reset_ignore'));
    }
}
