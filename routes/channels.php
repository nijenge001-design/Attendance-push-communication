<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (Laravel Reverb)
|--------------------------------------------------------------------------
|
| Private channel authorization for Sanctum-authenticated users.
| Channel names omit the "private-" prefix (Laravel / Echo add it).
|
| Frontend examples:
|   Echo.private('devices')
|   Echo.private('device.PSS7234900035')
|   Echo.private('site.' + siteId)
|
*/

// ── Global resource channels ───────────────────────────────────────────

Broadcast::channel('devices', function (User $user) {
    return $user->isAdmin()
        || $user->canManageDevices()
        || $user->canApproveDevices()
        || $user->canReadAttendance();
});

Broadcast::channel('attendance', function (User $user) {
    return $user->isAdmin() || $user->canReadAttendance();
});

Broadcast::channel('attendees', function (User $user) {
    return $user->isAdmin()
        || $user->canReadEmployees()
        || $user->canManageEmployees();
});

Broadcast::channel('commands', function (User $user) {
    return $user->isAdmin()
        || $user->canManageCommands()
        || $user->canManageDevices();
});

Broadcast::channel('import-errors', function (User $user) {
    return $user->isAdmin() || $user->canResolveImportErrors();
});

// ── Per-device: private-device.{serial} ─────────────────────────────────

Broadcast::channel('device.{serial}', function (User $user, string $serial) {
    if ($user->isAdmin()) {
        return true;
    }

    $device = Device::query()->where('serial_number', $serial)->first();

    if (! $device) {
        return false;
    }

    return $user->hasAccessToDevice($device);
});

// ── Per-site: private-site.{siteId} ────────────────────────────────────

Broadcast::channel('site.{siteId}', function (User $user, string $siteId) {
    if ($user->isAdmin()) {
        return true;
    }

    return $user->hasAccessToSite($siteId);
});
