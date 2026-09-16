<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\PendingCommand;
use App\Services\DeviceCommandQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('device:commands {serials?* : One or more device serials (omit for all)} {--clear : Clear pending commands} {--all : Show executed commands too} {--status= : Filter devices by status when listing all} {--limit=100 : Max rows to show}')]
#[Description('List or clear commands for one, many, or all devices')]
class ManageDeviceCommands extends Command
{
    public function handle(DeviceCommandQueue $queue): int
    {
        $serials = array_values(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $this->argument('serials') ?? []
        )));

        if ($serials === []) {
            $query = Device::query();
            if ($status = $this->option('status')) {
                $query->where('status', $status);
            }
            $serials = $query->pluck('serial_number')->all();

            if ($serials === []) {
                $this->warn('No devices found.');

                return self::SUCCESS;
            }
        }

        if ($this->option('clear')) {
            if (! $this->option('no-interaction') && ! $this->confirm('Clear pending commands for ' . count($serials) . ' device(s)?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }

            $total = 0;
            foreach ($serials as $serial) {
                $total += $queue->clearPending($serial);
            }

            $this->info("Cleared {$total} pending command(s).");

            return self::SUCCESS;
        }

        $query = PendingCommand::query()
            ->whereIn('device_serial', $serials)
            ->orderBy('device_serial')
            ->orderBy('id');

        if (! $this->option('all')) {
            $query->pending();
        }

        $commands = $query->limit((int) $this->option('limit'))->get();

        if ($commands->isEmpty()) {
            $this->info('No commands found.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'Device',
                'CmdID',
                'Command',
                'Executed',
                'Enabled',
                'Created',
                'Send Dur',
                'Sent At',
                'Exec Dur',
                'Next Run',
            ],
            $commands->map(fn ($cmd) => [
                $cmd->device_serial,
                $cmd->command_id,
                Str::limit((string) $cmd->command_text, 36),
                $cmd->executed ? 'Yes' : 'No',
                $cmd->enabled ? 'Yes' : 'No',
                $cmd->created_at?->format('m-d H:i:s') ?? '-',
                $this->formatSendDuration($cmd),
                $cmd->sent_at?->format('m-d H:i:s') ?? '-',
                $this->formatExecDuration($cmd),
                $cmd->next_run_at?->format('m-d H:i') ?? '-',
            ])
        );

        $ready = PendingCommand::query()
            ->whereIn('device_serial', $serials)
            ->readyToSend()
            ->enabled()
            ->count();

        $this->newLine();
        $this->line('Devices : ' . count($serials));
        $this->line('Shown   : ' . $commands->count());
        $this->line("Ready to send right now: <info>{$ready}</info>");

        return self::SUCCESS;
    }

    /**
     * Time from created_at → sent_at
     */
    private function formatSendDuration(PendingCommand $cmd): string
    {
        if (! $cmd->created_at) {
            return '-';
        }

        // Not yet sent
        if (! $cmd->sent_at) {
            $seconds = $cmd->created_at->diffInSeconds(now());
            return $this->humanSeconds($seconds) . ' (wait)';
        }

        $seconds = $cmd->created_at->diffInSeconds($cmd->sent_at);

        return $this->humanSeconds($seconds);
    }

    /**
     * Time from sent_at → executed_at
     */
    private function formatExecDuration(PendingCommand $cmd): string
    {
        if (! $cmd->sent_at) {
            return '-';
        }

        // Still waiting for device reply
        if (! $cmd->executed_at) {
            $seconds = $cmd->sent_at->diffInSeconds(now());
            return $this->humanSeconds($seconds) . ' (pend)';
        }

        $seconds = $cmd->sent_at->diffInSeconds($cmd->executed_at);

        return $this->humanSeconds($seconds);
    }

    private function humanSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;
            return sprintf('%dm%02ds', $m, $s);
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return sprintf('%dh%02dm', $h, $m);
    }
}
