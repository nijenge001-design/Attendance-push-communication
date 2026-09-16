<?php

namespace App\Policies\Api\v1;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for Photo resources (user photos captured by devices).
 *
 * Photos are device-scoped. Access follows the device’s site.
 *
 * Permissions used:
 *  - ReadEmployees or ReadAttendance → list / view
 *  - ManageEmployees or ManageDevices → delete
 */
class PhotoPolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allowIf(
            $user->canReadEmployees() || $user->canReadAttendance(),
            'You are not allowed to list photos.',
        );
    }

    public function view(User $user, Photo $photo): Response
    {
        if (! $user->canReadEmployees() && ! $user->canReadAttendance()) {
            return Response::deny('You are not allowed to view photos.');
        }

        return $this->authorizeDevice($user, $photo, hide: true);
    }

    public function delete(User $user, Photo $photo): Response
    {
        if (! $user->canManageEmployees() && ! $user->canManageDevices()) {
            return Response::deny('You are not allowed to delete photos.');
        }

        return $this->authorizeDevice($user, $photo, hide: false);
    }
}
