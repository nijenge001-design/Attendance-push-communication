<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\BuildsMailData;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordChangedMail extends Mailable
{
    use BuildsMailData;

    public function __construct(
        public string $name,
        public string $email,
        public string $url,
    ) {}

    public static function for(User $user): self
    {
        return new self(
            name: self::firstName($user->name),
            email: (string) $user->email,
            url: self::loginUrl(),
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your password was changed');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-changed',
            text: 'emails.auth.password-changed-text',
        );
    }
}
