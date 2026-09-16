<?php

use App\Jobs\CleanupOrphanedBiosAndPhotos;
use App\Jobs\GenerateDailyAttendanceSummary;
use App\Jobs\ProcessRecurringCommands;
use App\Jobs\PruneSoftDeletedRecords;
use App\Jobs\RetryStuckCommands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes / Scheduled Tasks
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
*/

// Re-queue commands that were sent but never got a response
Schedule::job(new RetryStuckCommands)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Advance next_run_at and re-activate recurring commands
Schedule::job(new ProcessRecurringCommands)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Calculate daily attendance summaries (previous day)
Schedule::job(new GenerateDailyAttendanceSummary(Carbon::now()->subDay()))
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->onOneServer();

// Remove bio templates & photos that no longer have an owner
Schedule::job(new CleanupOrphanedBiosAndPhotos)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Permanently delete old soft-deleted records (default: older than 90 days)
Schedule::job(new PruneSoftDeletedRecords)
    ->weeklyOn(0, '04:00') // Sunday at 04:00
    ->withoutOverlapping()
    ->onOneServer();
