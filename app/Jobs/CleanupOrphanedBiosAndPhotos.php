<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attendee;
use App\Models\BioTemplate;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

#[Queue('maintenance')]
#[Tries(2)]
class CleanupOrphanedBiosAndPhotos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $pins = Attendee::withTrashed()->pluck('pin');

        $bios = BioTemplate::whereNotIn('pin', $pins)->delete();
        $photos = Photo::whereNotNull('pin')
            ->whereNotIn('pin', $pins)
            ->delete();

        Log::info('Orphaned bio/photo cleanup finished', [
            'bios_deleted' => $bios,
            'photos_deleted' => $photos,
        ]);
    }
}
