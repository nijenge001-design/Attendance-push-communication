<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Jobs\DispatchMailJob;
use App\Jobs\SendPasswordChangedEmail;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        DispatchMailJob::pushAuth(new SendPasswordChangedEmail($notifiable));

        return [];
    }
}
