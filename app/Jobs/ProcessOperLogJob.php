<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DeviceDataHandler;
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
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Queue('operlog')]
#[Tries(3)]
#[Timeout(300)]
#[Backoff(15, 60, 180)]
class ProcessOperLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $sn,
        public string $path,
    ) {
        $this->onConnection('rabbitmq');
        $this->onQueue('operlog');
    }

    public function handle(DeviceDataHandler $handler): void
    {

        Log::info("################################## Yahageze 003-00 #################################################");
        if (! Storage::disk('local')->exists($this->path)) {
            Log::channel('user_info')->error('OPERLOG file missing', [
                'sn'   => $this->sn,
                'path' => $this->path,
            ]);
            return;
        }

        $content = Storage::disk('local')->get($this->path);

        $this->saveDebugFile($content);

        $result = $handler->processOperLogContent($this->sn, $content);

        Log::channel('user_info')->info('ProcessOperLogJob done', [
            'sn'     => $this->sn,
            'result' => $result,
            'bytes'  => strlen($content),
        ]);
    }

    private function saveDebugFile(string $content): void
    {
        if (! config('devices.debug_dump', false) && ! config('app.debug')) {
            return;
        }

        $debugPath = sprintf(
            'debug/operlog/%s/%s.txt',
            $this->sn,
            now()->format('Y-m-d_His_u')
        );

        $header = "SN: {$this->sn}\nTime: " . now()->toDateTimeString()
            . "\nBytes: " . strlen($content) . "\n" . str_repeat('-', 60) . "\n\n";

        Storage::disk('local')->put($debugPath, $header . $content);
    }

    public function failed(Throwable $e): void
    {
        Log::channel('user_info')->error('ProcessOperLogJob failed permanently', [
            'sn'    => $this->sn,
            'path'  => $this->path,
            'error' => $e->getMessage(),
        ]);
    }
}
