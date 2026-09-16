<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\PendingCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('device:commands-watch
    {serials?* : Optional device serial numbers (omit = all matching status)}
    {--status= : Filter devices by status (approved, pending, blocked)}
    {--all : Include executed commands}
    {--ready : Only commands ready to send}
    {--stuck : Only stuck (sent, not ACKed) commands}
    {--stuck-minutes=10 : Minutes without ACK to treat as stuck}
    {--interval=5 : Refresh interval in seconds}
    {--limit=50 : Max rows to show per refresh}')]
#[Description('Live-watch device command queue (all, by status, or by SN list)')]
class WatchDeviceCommands extends Command
{
    public function handle(): int
    {
        $interval = max(2, (int) $this->option('interval'));
        $limit = max(1, (int) $this->option('limit'));
        $stuckMinutes = max(1, (int) $this->option('stuck-minutes'));

        $this->info("Watching device commands every {$interval}s (Ctrl+C to stop)...");
        $this->newLine();

        while (true) {
            if (PHP_OS_FAMILY === 'Windows') {
                system('cls');
            } else {
                system('clear');
            }

            $this->line('<fg=cyan>Device Command Queue</>  ' . now()->toDateTimeString());
            $this->line(str_repeat('─', 78));

            $serials = $this->resolveSerials();

            if ($serials === []) {
                $this->warn('No devices match the current filters.');
                sleep($interval);
                continue;
            }

            $query = PendingCommand::query()
                ->whereIn('device_serial', $serials)
                ->orderBy('device_serial')
                ->orderByDesc('id');

            if ($this->option('ready')) {
                $query->readyToSend()->enabled();
            } elseif ($this->option('stuck')) {
                if (method_exists(PendingCommand::class, 'scopeStuck')) {
                    $query->stuck($stuckMinutes);
                } else {
                    $query->where('executed', false)
                        ->whereNotNull('sent_at')
                        ->where('sent_at', '<', now()->subMinutes($stuckMinutes));
                }
            } elseif (! $this->option('all')) {
                $query->pending();
            }

            $commands = $query->limit($limit)->get();

            $pendingTotal = PendingCommand::query()
                ->whereIn('device_serial', $serials)
                ->pending()
                ->count();

            $readyTotal = PendingCommand::query()
                ->whereIn('device_serial', $serials)
                ->readyToSend()
                ->enabled()
                ->count();

            $stuckTotal = PendingCommand::query()
                ->whereIn('device_serial', $serials)
                ->when(
                    method_exists(PendingCommand::class, 'scopeStuck'),
                    fn ($q) => $q->stuck($stuckMinutes),
                    fn ($q) => $q->where('executed', false)
                        ->whereNotNull('sent_at')
                        ->where('sent_at', '<', now()->subMinutes($stuckMinutes))
                )
                ->count();

            $filterLabel = $this->filterLabel($serials);
            $this->line("Filter : {$filterLabel}");
            $this->line("Devices: " . count($serials)
                . "  |  Pending: <fg=yellow>{$pendingTotal}</>"
                . "  |  Ready: <fg=green>{$readyTotal}</>"
                . "  |  Stuck: <fg=red>{$stuckTotal}</>");
            $this->newLine();

            if ($commands->isEmpty()) {
                $this->info('No commands in this view.');
            } else {
                $this->table(
                    ['Device', 'CmdID', 'Command', 'Exec', 'En', 'Sent', 'Next Run', 'Created'],
                    $commands->map(fn ($cmd) => [
                        $cmd->device_serial,
                        $cmd->command_id,
                        Str::limit((string) $cmd->command_text, 50),
                        $cmd->executed ? 'Y' : 'N',
                        $cmd->enabled ? 'Y' : 'N',
                        $cmd->sent_at?->format('H:i:s') ?? '-',
                        $cmd->next_run_at?->format('H:i:s') ?? '-',
                        $cmd->created_at?->format('H:i:s') ?? '-',
                    ])
                );
                $this->line('Showing ' . $commands->count() . " row(s) (limit {$limit}).");
            }

            sleep($interval);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSerials(): array
    {
        $serials = array_values(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $this->argument('serials') ?? []
        )));

        if ($serials !== []) {
            return $serials;
        }

        $query = Device::query();
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        return $query->pluck('serial_number')->all();
    }

    /**
     * @param  list<string>  $serials
     */
    private function filterLabel(array $serials): string
    {
        $parts = [];

        if ($this->argument('serials')) {
            $parts[] = 'SN: ' . implode(', ', array_slice($serials, 0, 5))
                . (count($serials) > 5 ? '…' : '');
        } elseif ($status = $this->option('status')) {
            $parts[] = "status={$status}";
        } else {
            $parts[] = 'all devices';
        }

        if ($this->option('ready')) {
            $parts[] = 'ready only';
        } elseif ($this->option('stuck')) {
            $parts[] = 'stuck only';
        } elseif ($this->option('all')) {
            $parts[] = 'including executed';
        } else {
            $parts[] = 'pending only';
        }

        return implode(' · ', $parts);
    }
}
