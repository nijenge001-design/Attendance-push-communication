<?php

namespace App\Policies\Api\v1;

use App\Models\Device;
use App\Models\PendingCommand;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for PendingCommand resources (device command queue).
 *
 * Commands are device-scoped. Listing is filtered by the sites the
 * user can access in the controller; here we gate the ability itself.
 *
 * Permissions used:
 *  - ManageCommands → create / update / enable / disable / delete
 *  - ManageDevices  → also allowed to list & view (operators who manage devices)
 */
class PendingCommandPolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allowIf(
            $user->canManageCommands() || $user->canManageDevices(),
            'You are not allowed to list device commands.',
        );
    }

    public function view(User $user, PendingCommand $pendingCommand): Response
    {
        if (! $user->canManageCommands() && ! $user->canManageDevices()) {
            return Response::deny('You are not allowed to view device commands.');
        }

        return $this->authorizeDevice($user, $pendingCommand, hide: true);
    }

    public function create(User $user, ?Device $device = null): Response
    {
        if (! $user->canManageCommands()) {
            return Response::deny('You are not allowed to queue device commands.');
        }

        return $device
            ? $this->authorizeDevice($user, $device, hide: false)
            : Response::deny('A target device is required.');
    }

    public function update(User $user, PendingCommand $pendingCommand): Response
    {
        if (! $user->canManageCommands()) {
            return Response::deny('You are not allowed to update device commands.');
        }

        return $this->authorizeDevice($user, $pendingCommand, hide: false);
    }

    public function delete(User $user, PendingCommand $pendingCommand): Response
    {
        return $this->update($user, $pendingCommand);
    }

    public function enable(User $user, PendingCommand $pendingCommand): Response
    {
        return $this->update($user, $pendingCommand);
    }

    public function disable(User $user, PendingCommand $pendingCommand): Response
    {
        return $this->update($user, $pendingCommand);
    }
}
