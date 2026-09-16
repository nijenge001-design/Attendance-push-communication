<?php

declare(strict_types=1);

use App\Http\Controllers\Api\v1\AttendanceLogController;
use App\Http\Controllers\Api\v1\AttendeeController;
use App\Http\Controllers\Api\v1\AttendeeImportErrorController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\BioTemplateController;
use App\Http\Controllers\Api\v1\DeviceController;
use App\Http\Controllers\Api\v1\MailPreviewController;
use App\Http\Controllers\Api\v1\PendingCommandController;
use App\Http\Controllers\Api\v1\PhotoController;
use App\Http\Controllers\Api\v1\SiteController;
use App\Http\Controllers\Api\v1\UserController;
use App\Http\Controllers\Api\v1\UserPermissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Prefixed with /api (bootstrap/app.php). Auth: Laravel Sanctum.
|
| Every route is named (Laravel convention). Password URIs match Breeze:
|   POST /api/v1/forgot-password     password.email
|   POST /api/v1/password/email      password.email.legacy
|   POST /api/v1/reset-password      password.update
|   POST /api/v1/password/reset      password.reset
|
| Octane (frankenphp) loads this file once per worker. After editing
| routes run: php artisan octane:reload
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Public auth (Breeze / Fortify names, plus aliases) ────────────
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');

    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::post('password/email', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('password.email.legacy');

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1')
        ->name('password.update');

    Route::post('password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1')
        ->name('password.reset');

    Route::get('mail/preview/{template}', [MailPreviewController::class, 'show'])
        ->name('mail.preview');

    // ── Authenticated ─────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('me', [AuthController::class, 'me'])->name('user.show');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout.all');
        Route::post('change-password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:10,1')
            ->name('password.change');
        Route::put('password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:10,1')
            ->name('password.change.legacy');

        // Sites
        Route::apiResource('sites', SiteController::class);

        // Devices (index/show/destroy by serial or UUID; nested create/update under site)
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('sites/{site}/devices', [DeviceController::class, 'store'])->name('sites.devices.store');
        Route::post('sites/{site}/devices/bulk-assign', [DeviceController::class, 'bulkAssignToSite'])->name('sites.devices.bulk-assign');
        Route::post('devices/bulk-assign', [DeviceController::class, 'bulkAssign'])->name('devices.bulk-assign');
        Route::post('sites/{site}/devices/{device}/assign', [DeviceController::class, 'assign'])->name('sites.devices.assign');
        Route::post('devices/{device}/assign-site', [DeviceController::class, 'assignSite'])->name('devices.assign-site');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
        Route::match(['put', 'patch'], 'sites/{site}/devices/{device}', [DeviceController::class, 'update'])
            ->name('sites.devices.update');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('devices/{device}/approve', [DeviceController::class, 'approve'])->name('devices.approve');
        Route::post('devices/{device}/block', [DeviceController::class, 'block'])->name('devices.block');
        Route::get('devices/{device}/attendees', [DeviceController::class, 'attendees'])->name('devices.attendees.index');
        Route::post('devices/{device}/sync-users', [DeviceController::class, 'syncUsers'])->name('devices.attendees.sync');

        // Attendees
        Route::post('attendees/bulk', [AttendeeController::class, 'bulkStore'])->name('attendees.bulk.store');
        Route::post('attendees/bulk-access', [AttendeeController::class, 'bulkAccess'])->name('attendees.bulk.access');
        Route::get('attendees/import-template', [AttendeeController::class, 'importTemplate'])->name('attendees.import.template');
        Route::post('attendees/import', [AttendeeController::class, 'import'])->name('attendees.import');
        Route::apiResource('attendees', AttendeeController::class);
        Route::post('attendees/{attendee}/grant-device-access', [AttendeeController::class, 'grantDeviceAccess'])
            ->name('attendees.access.grant');
        Route::post('attendees/{attendee}/revoke-device-access', [AttendeeController::class, 'revokeDeviceAccess'])
            ->name('attendees.access.revoke');

        // Pending commands
        Route::get('pending-commands', [PendingCommandController::class, 'index'])->name('pending-commands.index');
        Route::post('devices/{device}/commands', [PendingCommandController::class, 'store'])
            ->name('devices.commands.store');

        // Attendance logs
        Route::get('attendance-logs', [AttendanceLogController::class, 'index'])->name('attendance-logs.index');
        Route::post('attendance-logs/bulk', [AttendanceLogController::class, 'bulkStore'])->name('attendance-logs.bulk');
        Route::get('attendance-logs/import-template', [AttendanceLogController::class, 'importTemplate'])
            ->name('attendance-logs.import.template');
        Route::post('attendance-logs/import', [AttendanceLogController::class, 'import'])->name('attendance-logs.import');
        Route::get('attendance-logs/{attendanceLog}', [AttendanceLogController::class, 'show'])->name('attendance-logs.show');
        Route::post('devices/{device}/attendance-logs/bulk', [AttendanceLogController::class, 'bulkStoreForDevice'])
            ->name('devices.attendance-logs.bulk');
        Route::post('devices/{device}/attendance-logs', [AttendanceLogController::class, 'store'])
            ->name('devices.attendance-logs.store');
        Route::delete('attendance-logs/{attendanceLog}', [AttendanceLogController::class, 'destroy'])
            ->name('attendance-logs.destroy');

        // Bio templates
        Route::get('bio-templates', [BioTemplateController::class, 'index'])->name('bio-templates.index');
        Route::get('bio-templates/{bioTemplate}', [BioTemplateController::class, 'show'])->name('bio-templates.show');
        Route::delete('bio-templates/{bioTemplate}', [BioTemplateController::class, 'destroy'])->name('bio-templates.destroy');

        // Photos
        Route::get('photos', [PhotoController::class, 'index'])->name('photos.index');
        Route::get('photos/{photo}', [PhotoController::class, 'show'])->name('photos.show');
        Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

        // Attendee import errors
        Route::post('attendee-import-errors/bulk', [AttendeeImportErrorController::class, 'bulkUpdate'])
            ->name('attendee-import-errors.bulk');
        Route::get('attendee-import-errors', [AttendeeImportErrorController::class, 'index'])
            ->name('attendee-import-errors.index');
        Route::get('attendee-import-errors/{attendeeImportError}', [AttendeeImportErrorController::class, 'show'])
            ->name('attendee-import-errors.show');
        Route::post('attendee-import-errors/{attendeeImportError}/resolve', [AttendeeImportErrorController::class, 'resolve'])
            ->name('attendee-import-errors.resolve');
        Route::post('attendee-import-errors/{attendeeImportError}/ignore', [AttendeeImportErrorController::class, 'ignore'])
            ->name('attendee-import-errors.ignore');

        // Users
        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.password.update');

        // User permission overrides
        Route::get('users/{user}/permissions/effective', [UserPermissionController::class, 'effective'])
            ->name('users.permissions.effective');
        Route::get('users/{user}/permissions', [UserPermissionController::class, 'index'])
            ->name('users.permissions.index');
        Route::post('users/{user}/permissions', [UserPermissionController::class, 'store'])
            ->name('users.permissions.store');
        Route::match(['put', 'patch'], 'users/{user}/permissions/{userPermission}', [UserPermissionController::class, 'update'])
            ->name('users.permissions.update');
        Route::delete('users/{user}/permissions/{userPermission}', [UserPermissionController::class, 'destroy'])
            ->name('users.permissions.destroy');
    });
});
