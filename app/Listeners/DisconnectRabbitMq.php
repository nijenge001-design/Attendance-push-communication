<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\DispatchMailJob;

/**
 * Close + drop the cached AMQP connection after each Octane request.
 * Do not destroy the queue manager — that unregisters the rabbitmq connector.
 */
class DisconnectRabbitMq
{
    public function handle(object $event): void
    {
        DispatchMailJob::release();
    }
}
