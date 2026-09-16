<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Jobs\DispatchMailJob;
use App\Jobs\SendResetPasswordEmail;
use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Password broker calls User::sendPasswordResetNotification().
 * If something still notify()s this class, publish the same job
 * through DispatchMailJob (Octane-safe).
 */
class ResetPasswordNotification extends ResetPassword
{
    public function via(mixed $notifiable): array
    {
        DispatchMailJob::pushAuth(new SendResetPasswordEmail($notifiable, $this->token));

        return [];
    }
}
