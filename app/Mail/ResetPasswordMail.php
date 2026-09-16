<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\BuildsMailData;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends Mailable
{
    use BuildsMailData;

    public function __construct(
        public string $name,
        public string $email,
        public string $url,
        public int $minutes,
    ) {}

    public static function for(User $user, string $token): self
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);
        $url = self::frontendUrl((string) config('api.password_reset_path', '/reset-password')).'?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);

        return new self(
            name: self::firstName($user->name),
            email: (string) $user->email,
            url: $url,
            minutes: $minutes,
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.reset-password',
            text: 'emails.auth.reset-password-text',
        );
    }
}
