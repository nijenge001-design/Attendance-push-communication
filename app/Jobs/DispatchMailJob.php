<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue as QueueAttribute;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use ReflectionClass;
use ReflectionObject;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

/**
 * Octane-safe publisher for every ShouldQueue job (mail, operlog,
 * attendance, attendees, devices, …).
 *
 * Do not app()->forgetInstance('queue') — that drops the rabbitmq
 * connector the package registered on boot ("No connector for [rabbitmq]").
 *
 * Do not Job::dispatch() from HTTP/Octane — use DispatchMailJob::push().
 */
final class DispatchMailJob
{
    /** rabbitmq | sync | null */
    public static ?string $lastVia = null;

    public static ?string $lastError = null;

    /**
     * Auth mail (reset / changed / welcome). Delivered in this request
     * unless MAIL_QUEUE_AUTH=true. Queueing without a notifications
     * worker is why reset emails never arrived.
     */
    public static function pushAuth(ShouldQueue $job): void
    {
        self::$lastError = null;

        if (config('api.mail_queue_auth')) {
            self::push($job);

            return;
        }

        try {
            dispatch_sync($job);
            self::$lastVia = 'sync';
        } catch (Throwable $e) {
            self::$lastVia = null;
            self::$lastError = $e->getMessage();
            Log::error('Auth mail send failed', [
                'job' => $job::class,
                'mailer' => config('mail.default'),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function push(ShouldQueue $job, ?string $queue = null): void
    {
        self::$lastVia = null;

        $queue = $queue ?? self::resolveQueue($job);

        if (method_exists($job, 'onQueue')) {
            $job->onQueue($queue);
        }
        if (method_exists($job, 'onConnection')) {
            $job->onConnection('rabbitmq');
        }

        try {
            self::refreshConnection();
            self::publish($job, $queue);
            self::$lastVia = 'rabbitmq';
        } catch (AMQPChannelClosedException|AMQPConnectionClosedException $e) {
            Log::warning('RabbitMQ channel closed; reconnecting and retrying mail dispatch.', [
                'job' => $job::class,
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            self::refreshConnection();
            try {
                self::publish($job, $queue);
                self::$lastVia = 'rabbitmq';
            } catch (Throwable $retry) {
                Log::error('RabbitMQ publish failed after reconnect; sending job synchronously.', [
                    'job' => $job::class,
                    'error' => $retry->getMessage(),
                ]);
                try {
                    dispatch_sync($job);
                    self::$lastVia = 'sync';
                } catch (Throwable $sync) {
                    Log::error('Sync fallback failed; HTTP request will continue.', [
                        'job' => $job::class,
                        'error' => $sync->getMessage(),
                    ]);
                    self::$lastVia = null;
                }
            }
        } catch (Throwable $e) {
            Log::error('RabbitMQ publish failed; sending job synchronously.', [
                'job' => $job::class,
                'error' => $e->getMessage(),
            ]);
            try {
                dispatch_sync($job);
                self::$lastVia = 'sync';
            } catch (Throwable $sync) {
                Log::error('Sync fallback failed; HTTP request will continue.', [
                    'job' => $job::class,
                    'error' => $sync->getMessage(),
                ]);
                self::$lastVia = null;
            }
        }
    }

    public static function release(): void
    {
        $manager = app('queue');
        $cached = self::cachedConnection($manager);

        if ($cached === null) {
            return;
        }

        try {
            if (method_exists($cached, 'close')) {
                $cached->close();
            }
        } catch (Throwable) {
            //
        }

        self::forgetCachedConnection($manager);
    }

    private static function resolveQueue(ShouldQueue $job): string
    {
        if (isset($job->queue) && is_string($job->queue) && $job->queue !== '') {
            return $job->queue;
        }

        foreach ((new ReflectionClass($job))->getAttributes(QueueAttribute::class) as $attribute) {
            $instance = $attribute->newInstance();

            return $instance->queue ?? $instance->name ?? 'default';
        }

        return (string) config('queue.connections.rabbitmq.queue', 'default');
    }

    private static function publish(ShouldQueue $job, string $queue): void
    {
        app('queue')->connection('rabbitmq')->push($job, '', $queue);

        Log::info('Job published to RabbitMQ', [
            'job' => $job::class,
            'queue' => $queue,
            'connection' => 'rabbitmq',
        ]);
    }

    private static function refreshConnection(): void
    {
        self::ensureConnector();

        $manager = app('queue');
        $cached = self::cachedConnection($manager);

        if ($cached !== null) {
            try {
                if (method_exists($cached, 'close')) {
                    $cached->close();
                }
            } catch (Throwable) {
                //
            }

            self::forgetCachedConnection($manager);
        }

        // New RabbitMQQueue instance; AMQPLazyConnection opens on first publish.
        $manager->connection('rabbitmq');
    }

    private static function ensureConnector(): void
    {
        $manager = app('queue');
        $ref = new ReflectionObject($manager);

        if (! $ref->hasProperty('connectors')) {
            return;
        }

        $prop = $ref->getProperty('connectors');
        $connectors = $prop->getValue($manager) ?? [];

        if (isset($connectors['rabbitmq'])) {
            return;
        }

        $manager->addConnector('rabbitmq', function () {
            return new RabbitMQConnector(app('events'));
        });
    }

    private static function cachedConnection(object $manager): ?QueueContract
    {
        $ref = new ReflectionObject($manager);

        if (! $ref->hasProperty('connections')) {
            return null;
        }

        $prop = $ref->getProperty('connections');
        $connections = $prop->getValue($manager) ?? [];

        $connection = $connections['rabbitmq'] ?? null;

        return $connection instanceof QueueContract ? $connection : null;
    }

    private static function forgetCachedConnection(object $manager): void
    {
        $ref = new ReflectionObject($manager);

        if (! $ref->hasProperty('connections')) {
            return;
        }

        $prop = $ref->getProperty('connections');
        $connections = $prop->getValue($manager) ?? [];
        unset($connections['rabbitmq']);
        $prop->setValue($manager, $connections);
    }
}
