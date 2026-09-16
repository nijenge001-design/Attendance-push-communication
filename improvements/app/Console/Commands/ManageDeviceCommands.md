```php
<?php

declare(strict_types=1);

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
            ['Device', 'CmdID', 'Command', 'Executed', 'Enabled', 'Sent At', 'Next Run', 'Created'],
            $commands->map(fn ($cmd) => [
                $cmd->device_serial,
                $cmd->command_id,
                Str::limit((string) $cmd->command_text, 40),
                $cmd->executed ? 'Yes' : 'No',
                $cmd->enabled ? 'Yes' : 'No',
                $cmd->sent_at?->toDateTimeString() ?? '-',
                $cmd->next_run_at?->toDateTimeString() ?? '-',
                $cmd->created_at?->toDateTimeString() ?? '-',
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
}
