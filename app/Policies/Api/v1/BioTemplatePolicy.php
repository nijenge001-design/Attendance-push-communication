<?php

namespace App\Policies\Api\v1;

use App\Models\BioTemplate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for BioTemplate resources (fingerprint, face, vein, …).
 *
 * Templates are device-scoped. Access follows the device’s site.
 *
 * Permissions used:
 *  - ReadEmployees or ReadAttendance → list / view
 *  - ManageEmployees or ManageDevices → delete
 */
class BioTemplatePolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allowIf(
            $user->canReadEmployees() || $user->canReadAttendance(),
            'You are not allowed to list bio templates.',
        );
    }

    public function view(User $user, BioTemplate $bioTemplate): Response
    {
        if (! $user->canReadEmployees() && ! $user->canReadAttendance()) {
            return Response::deny('You are not allowed to view bio templates.');
        }

        return $this->authorizeDevice($user, $bioTemplate, hide: true);
    }

    public function delete(User $user, BioTemplate $bioTemplate): Response
    {
        if (! $user->canManageEmployees() && ! $user->canManageDevices()) {
            return Response::deny('You are not allowed to delete bio templates.');
        }

        return $this->authorizeDevice($user, $bioTemplate, hide: false);
    }
}
