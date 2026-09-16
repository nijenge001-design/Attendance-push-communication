<?php

namespace App\Models;

use App\Policies\Api\v1\PendingCommandPolicy;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $device_serial
 * @property string $command_id
 * @property string $command_text
 * @property bool $executed
 * @property array|null $result
 * @property Carbon|null $sent_at
 * @property Carbon|null $executed_at
 * @property Carbon|null $scheduled_at
 * @property string|null $recurrence
 * @property Carbon|null $next_run_at
 * @property bool $is_recurring
 * @property bool $enabled
 * @property Carbon|null $enabled_at
 * @property int|null $return_code
 * @property int $retry_count
 * @property int $max_retries
 * @property Carbon|null $last_retry_at
 * @property Carbon|null $available_at
 */
#[UsePolicy(PendingCommandPolicy::class)]
#[Fillable(['device_serial', 'command_id', 'command_text', 'executed', 'result', 'sent_at', 'executed_at', 'scheduled_at', 'recurrence', 'next_run_at', 'is_recurring', 'enabled', 'enabled_at', 'return_code', 'retry_count', 'max_retries', 'last_retry_at', 'available_at'])]
class PendingCommand extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'executed' => 'boolean',
            'is_recurring' => 'boolean',
            'enabled' => 'boolean',
            'sent_at' => 'datetime',
            'executed_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'next_run_at' => 'datetime',
            'enabled_at' => 'datetime',
            'return_code' => 'integer',
            'result' => 'array',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'last_retry_at' => 'datetime',
            'available_at' => 'datetime',
        ];
    }

    // =====================================================================
    // Relationships
    // =====================================================================

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_serial', 'serial_number');
    }

    // =====================================================================
    // Scopes
    // =====================================================================
    public function scopeAccessibleBy($query, User $user)
    {
        $siteIds = $user->accessibleSiteIds();

        if ($siteIds === null) {
            return $query;
        }

        return $query->whereIn(
            'device_serial',
            Device::whereIn('site_id', $siteIds)->select('serial_number')
        );
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('executed', false);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeExecuted(Builder $query): Builder
    {
        return $query->where('executed', true);
    }

    public function scopeForDevice(Builder $query, string $serial): Builder
    {
        return $query->where('device_serial', $serial);
    }

    public function scopeNotSent(Builder $query): Builder
    {
        return $query->whereNull('sent_at');
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Commands that are ready to be sent to the device right now (including retries).
     */
    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->where('executed', false)
            ->where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            })
            ->where(function ($q) {
                // One-time or scheduled
                $q->where(function ($q2) {
                    $q2->where('is_recurring', false)
                        ->where(function ($q3) {
                            $q3->whereNull('scheduled_at')
                                ->orWhere('scheduled_at', '<=', now());
                        });
                })
                    // Recurring and due
                    ->orWhere(function ($q2) {
                        $q2->where('is_recurring', true)
                            ->where(function ($q3) {
                                $q3->whereNull('next_run_at')
                                    ->orWhere('next_run_at', '<=', now());
                            });
                    });
            });
    }

    /**
     * Commands that were sent but never got a response (stuck).
     */
    public function scopeStuck(Builder $query, int $minutes = 10): Builder
    {
        return $query->where('executed', false)
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', now()->subMinutes($minutes))
            ->whereColumn('retry_count', '<', 'max_retries');
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    public function markAsSent(): bool
    {
        return $this->update(['sent_at' => now()]);
    }

    public function enable(): bool
    {
        return $this->update(['enabled' => true, 'enabled_at' => now()]);
    }

    public function disable(): bool
    {
        return $this->update(['enabled' => false, 'enabled_at' => null]);
    }

    /**
     * Mark command as executed.
     *
     * @param array|string|null $result Structured result or plain message
     */
    public function markAsExecuted(array|string|null $result = null, ?int $returnCode = null): bool
    {
        $data = [
            'executed' => true,
            'executed_at' => now(),
        ];

        if ($result !== null) {
            $data['result'] = is_array($result)
                ? $result
                : ['message' => (string)$result];
        }

        if ($returnCode !== null) {
            $data['return_code'] = $returnCode;
        }

        return $this->update($data);
    }

    /**
     * Schedule a retry after failure or timeout.
     */
    public function scheduleRetry(int $delayMinutes = 5): bool
    {
        if ($this->retry_count >= $this->max_retries) {
            // Give up
            return $this->update([
                'executed' => true,
                'result' => array_merge($this->result ?? [], [
                    'error' => 'Max retries exceeded',
                ]),
                'return_code' => $this->return_code ?? -1,
                'executed_at' => now(),
            ]);
        }

        return $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
            'sent_at' => null, // allow it to be sent again
            'available_at' => now()->addMinutes($delayMinutes),
            'result' => array_merge($this->result ?? [], [
                'last_error' => 'Scheduled for retry',
            ]),
        ]);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isExecuted(): bool
    {
        return $this->executed === true;
    }

    /**
     * Calculate the next run time from the recurrence value.
     */
    public function calculateNextRun(?Carbon $from = null): Carbon
    {
        $from = $from ?? now();

        // Support simple keywords
        $simple = match (strtolower((string)$this->recurrence)) {
            'hourly' => $from->copy()->addHour(),
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            'monthly' => $from->copy()->addMonth(),
            default => null,
        };

        if ($simple) {
            return $simple;
        }

        // Full cron expression support
        try {
            $cron = new CronExpression((string)$this->recurrence);

            return Carbon::instance($cron->getNextRunDate($from));
        } catch (\Exception) {
            // Fallback – run again in 1 day
            return $from->copy()->addDay();
        }
    }
}
