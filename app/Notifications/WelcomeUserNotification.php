<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Jobs\DispatchMailJob;
use App\Jobs\SendWelcomeUserEmail;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification
{
    public function via(object $notifiable): array
    {
        DispatchMailJob::pushAuth(new SendWelcomeUserEmail($notifiable));

        return [];
    }
}
