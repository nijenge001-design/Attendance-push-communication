<?php

namespace App\Policies\Api\v1;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for AttendanceLog resources.
 *
 * Logs are device-scoped (they carry a device_serial).
 * Access follows the device’s site.
 *
 * Permissions used:
 *  - ReadAttendance   → list / view
 *  - ManageEmployees or ManageDevices → manual creation
 *
 * Delete is reserved for administrators only.
 */
class AttendanceLogPolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allowIf(
            $user->canReadAttendance(),
            'You are not allowed to view attendance logs.',
        );
    }

    public function view(User $user, AttendanceLog $attendanceLog): Response
    {
        if (! $user->canReadAttendance()) {
            return Response::deny('You are not allowed to view attendance logs.');
        }

        return $this->authorizeDevice($user, $attendanceLog, hide: true);
    }

    public function create(User $user, ?Device $device = null): Response
    {
        if (! $user->canManageEmployees() && ! $user->canManageDevices()) {
            return Response::deny('You are not allowed to create attendance logs.');
        }

        return $device
            ? $this->authorizeDevice($user, $device, hide: false)
            : Response::deny('A target device is required.');
    }

    public function createBulk(User $user): Response
    {
        return $this->allowIf(
            $user->canManageEmployees() || $user->canManageDevices(),
            'You are not allowed to create attendance logs.',
        );
    }

    public function delete(User $user, AttendanceLog $attendanceLog): Response
    {
        return Response::deny('Only administrators can delete attendance logs.');
    }
}
