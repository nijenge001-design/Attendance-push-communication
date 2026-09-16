<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\BuildsMailData;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeUserMail extends Mailable
{
    use BuildsMailData;

    public function __construct(
        public string $name,
        public string $username,
        public string $role,
        public string $url,
    ) {}

    public static function for(User $user): self
    {
        $role = $user->role;

        return new self(
            name: self::firstName($user->name),
            username: (string) $user->username,
            role: is_object($role) && method_exists($role, 'label') ? $role->label() : (string) $role,
            url: self::loginUrl(),
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your account is ready');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.welcome',
            text: 'emails.auth.welcome-text',
        );
    }
}
