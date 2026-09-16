<?php

namespace App\Policies\Api\v1;

use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for Device resources.
 *
 * Admins (`User::isAdmin()`) are allowed every ability via Policy::before()
 * — list, assign, approve, block, delete, sync. Do not re-check admin here.
 *
 * Multi-tenancy: Devices belong to a Site. Non-admins are granted access
 * when they can access that site.
 *
 * Permissions used:
 *  - ManageDevices  → create / update / assign
 *  - ApproveDevices → approve / block
 *  - ReadAttendance → also allowed to list & view (read-only consumers)
 *
 * Destructive delete is reserved for administrators only.
 */
class DevicePolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allowIf(
            $user->canManageDevices() || $user->canApproveDevices() || $user->canReadAttendance(),
            'You are not allowed to list devices.',
        );
    }

    public function view(User $user, Device $device): Response
    {
        if (! $user->canManageDevices() && ! $user->canApproveDevices() && ! $user->canReadAttendance()) {
            return Response::deny('You are not allowed to view devices.');
        }

        return $this->authorizeDevice($user, $device, hide: true);
    }

    public function create(User $user, ?Site $site = null): Response
    {
        if (! $user->canManageDevices()) {
            return Response::deny('You are not allowed to create devices.');
        }

        return $this->authorizeSite($user, $site?->id, hide: false);
    }

    public function update(User $user, Device $device, ?Site $site = null): Response
    {
        if (! $user->canManageDevices()) {
            return Response::deny('You are not allowed to update devices.');
        }

        $deviceAccess = $this->authorizeDevice($user, $device, hide: false);
        if ($deviceAccess->denied()) {
            return $deviceAccess;
        }

        return $this->authorizeSite($user, $site?->id, hide: false);
    }

    public function assign(User $user, Device $device, Site $site): Response
    {
        if (! $user->canManageDevices()) {
            return Response::deny('You are not allowed to assign devices to sites.');
        }

        if ($device->site_id) {
            $current = $this->authorizeDevice($user, $device, hide: false);
            if ($current->denied()) {
                return $current;
            }
        }

        return $this->authorizeSite($user, $site->id, hide: false);
    }

    public function assignAny(User $user, ?Site $site = null): Response
    {
        if (! $user->canManageDevices()) {
            return Response::deny('You are not allowed to assign devices to sites.');
        }

        return $this->authorizeSite($user, $site?->id, hide: false);
    }

    public function delete(User $user, Device $device): Response
    {
        return Response::deny('Only administrators can delete devices.');
    }

    public function approve(User $user, Device $device): Response
    {
        if (! $user->canApproveDevices()) {
            return Response::deny('You are not allowed to approve devices.');
        }

        return $this->authorizeDevice($user, $device, hide: false);
    }

    public function block(User $user, Device $device): Response
    {
        if (! $user->canApproveDevices()) {
            return Response::deny('You are not allowed to block devices.');
        }

        return $this->authorizeDevice($user, $device, hide: false);
    }

    public function syncUsers(User $user, Device $device): Response
    {
        if (! $user->canManageDevices()) {
            return Response::deny('You are not allowed to pull users from devices.');
        }

        return $this->authorizeDevice($user, $device, hide: false);
    }
}
