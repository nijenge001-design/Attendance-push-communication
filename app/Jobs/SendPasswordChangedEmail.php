<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Queue('notifications')]
#[Tries(3)]
#[Timeout(60)]
#[Backoff(10, 30)]
class SendPasswordChangedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        $this->onConnection('rabbitmq');
        $this->onQueue((string) config('api.mail_queue', 'notifications'));
    }

    public function handle(): void
    {
        if (! filled($this->user->email)) {
            return;
        }

        Mail::to($this->user->email, $this->user->name)
            ->send(PasswordChangedMail::for($this->user));
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Password changed email failed', [
            'user_id' => $this->user->id,
            'error' => $e?->getMessage(),
        ]);
    }
}
