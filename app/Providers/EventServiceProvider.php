<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AttendancePunched;
use App\Events\AttendeeAccessGranted;
use App\Events\AttendeeAccessRevoked;
use App\Events\AttendeeCreated;
use App\Events\AttendeeDeleted;
use App\Events\AttendeeImportErrorCreated;
use App\Events\AttendeeUpdated;
use App\Events\DeviceApproved;
use App\Events\DeviceInfoUpdated;
use App\Events\DeviceOffline;
use App\Listeners\DispatchDeviceApprovedSideEffects;
use App\Listeners\DispatchDeviceInfoUpdated;
use App\Listeners\DispatchNotifyDeviceOffline;
use App\Listeners\DispatchProcessAttendanceLog;
use App\Listeners\DispatchProcessAttendeeImportError;
use App\Listeners\SyncAttendeeOnAccessGranted;
use App\Listeners\SyncAttendeeOnAccessRevoked;
use App\Listeners\SyncAttendeeOnCreated;
use App\Listeners\SyncAttendeeOnDeleted;
use App\Listeners\SyncAttendeeOnUpdated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Central event → listener map.
 *
 * Rules
 * -----
 * 1. Never register Job classes directly in $listen when the job constructor
 *    expects an Eloquent model. The container cannot build that job from an event.
 *    Use a thin Listener that calls Job::dispatch(...).
 *
 * 2. Prefer explicit $listen entries over Event::listen closures in boot()
 *    so mappings are discoverable and testable.
 *
 * 3. Side effects that talk to devices are always queued (via jobs).
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // ── Attendance ──────────────────────────────────────────────
        AttendancePunched::class => [
            DispatchProcessAttendanceLog::class,
        ],

        // ── Attendee lifecycle → device sync ────────────────────────
        AttendeeCreated::class => [
            SyncAttendeeOnCreated::class,
        ],
        AttendeeUpdated::class => [
            SyncAttendeeOnUpdated::class,
        ],
        AttendeeDeleted::class => [
            SyncAttendeeOnDeleted::class,
        ],
        AttendeeAccessGranted::class => [
            SyncAttendeeOnAccessGranted::class,
        ],
        AttendeeAccessRevoked::class => [
            SyncAttendeeOnAccessRevoked::class,
        ],

        // ── Device ──────────────────────────────────────────────────
        DeviceApproved::class => [
            DispatchDeviceApprovedSideEffects::class,
        ],
        DeviceOffline::class => [
            DispatchNotifyDeviceOffline::class,
        ],
        DeviceInfoUpdated::class => [
            DispatchDeviceInfoUpdated::class,
        ],

        // ── Import errors ───────────────────────────────────────────
        AttendeeImportErrorCreated::class => [
            DispatchProcessAttendeeImportError::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        // Explicit map above is the source of truth.
        return false;
    }
}
