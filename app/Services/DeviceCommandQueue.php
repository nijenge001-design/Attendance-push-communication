<?php

namespace App\Services;

use App\Events\CommandQueued;
use App\Models\Device;
use App\Models\PendingCommand;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Random\RandomException;

class DeviceCommandQueue
{
    public function queue(
        string             $serial,
        string             $command,
        ?DateTimeInterface $scheduledAt = null,
        ?string            $recurrence = null
    ): PendingCommand
    {
        $device = Device::where('serial_number', $serial)->firstOrFail();

        if (!str_starts_with(trim($command), 'C:')) {
            try {
                $command = CommandBuilder::generateCommand($command);
            } catch (RandomException $e) {
                $command = 'C:' . uniqid('cmd_', true) . ':' . $command;
            }
        }

        $commandId = CommandBuilder::extractCmdId($command);
        $isRecurring = !empty($recurrence);

        $nextRunAt = null;
        if ($isRecurring) {
            $nextRunAt = $scheduledAt
                ? Carbon::instance($scheduledAt)
                : now();
        }

        $pending = PendingCommand::create([
            'device_serial' => $device->serial_number,
            'command_id' => $commandId,
            'command_text' => $command,
            'executed' => false,
            'scheduled_at' => $scheduledAt,
            'recurrence' => $recurrence,
            'is_recurring' => $isRecurring,
            'next_run_at' => $nextRunAt,
        ]);

        event(new CommandQueued($pending));

        return $pending;
    }

    public function schedule(string $serial, string $command, DateTimeInterface $at): PendingCommand
    {
        return $this->queue($serial, $command, $at);
    }

    public function recur(string $serial, string $command, string $cronExpression): PendingCommand
    {
        return $this->queue($serial, $command, null, $cronExpression);
    }

    /**
     * Queue a command for a Device model instance.
     */
    public function queueForDevice(Device $device, string $command): PendingCommand
    {
        // Ensure the command has a CmdID
        if (!str_starts_with(trim($command), 'C:')) {
            try {
                $command = CommandBuilder::generateCommand($command);
            } catch (RandomException $e) {
                $command = 'C:' . uniqid('cmd_', true) . ':' . $command;
            }
        }

        $commandId = CommandBuilder::extractCmdId($command);

        $pending = PendingCommand::create([
            'device_serial' => $device->serial_number,
            'command_id' => $commandId,
            'command_text' => $command,
            'executed' => false,
        ]);

        event(new CommandQueued($pending));

        return $pending;
    }

    /**
     * Queue multiple commands at once.
     *
     * @param string[] $commands
     * @return Collection<int, PendingCommand>
     */
    public function queueMany(string $serial, array $commands): Collection
    {
        $device = Device::where('serial_number', $serial)->firstOrFail();

        return collect($commands)->map(
            fn(string $cmd) => $this->queueForDevice($device, $cmd)
        );
    }

    /**
     * Queue a FluentCommand instance.
     */
    public function queueFluent(string $serial, FluentCommand $fluent): PendingCommand
    {
        return $this->queue($serial, $fluent->generate());
    }

    /**
     * Get all pending commands for a device.
     */
    public function pending(string $serial): Collection
    {
        return PendingCommand::forDevice($serial)
            ->pending()
            ->orderBy('id')
            ->get();
    }

    /**
     * Clear all pending commands for a device.
     */
    public function clearPending(string $serial): int
    {
        return PendingCommand::forDevice($serial)
            ->pending()
            ->delete();
    }

    /**
     * Mark a command as executed by its CmdID.
     */
    public function markExecuted(string $commandId, ?string $result = null): bool
    {
        $cmd = PendingCommand::where('command_id', $commandId)->first();

        if (!$cmd) {
            return false;
        }

        return $cmd->markAsExecuted($result);
    }

    /**
     * Queue command(s) to devices filtered by status.
     *
     * @param string|array $commands Single command or array of commands
     * @param string|null $status pending|approved|blocked|null (= all)
     * @return int                       Number of commands queued
     */
    public function queueToDevicesByStatus(
        string|array        $commands,
        ?string             $status = Device::STATUS_APPROVED,
        ?\DateTimeInterface $scheduledAt = null,
        ?string             $recurrence = null
    ): int
    {
        $commands = is_array($commands) ? $commands : [$commands];

        $query = Device::query();
        if ($status !== null) {
            $query->where('status', $status);
        }

        $devices = $query->get();
        $total = 0;

        foreach ($devices as $device) {
            foreach ($commands as $command) {
                $this->queue(
                    $device->serial_number,
                    $command,
                    $scheduledAt,
                    $recurrence
                );
                $total++;
            }
        }

        return $total;
    }

    /**
     * Queue command(s) to ALL devices (any status).
     */
    public function queueToAllDevices(string|array $commands): int
    {
        return $this->queueToDevicesByStatus($commands, null);
    }

    /**
     * Queue command(s) to all approved devices.
     */
    public function queueToApprovedDevices(string|array $commands): int
    {
        return $this->queueToDevicesByStatus($commands, Device::STATUS_APPROVED);
    }

    /**
     * Queue command(s) to all pending devices.
     */
    public function queueToPendingDevices(string|array $commands): int
    {
        return $this->queueToDevicesByStatus($commands, Device::STATUS_PENDING);
    }
}
