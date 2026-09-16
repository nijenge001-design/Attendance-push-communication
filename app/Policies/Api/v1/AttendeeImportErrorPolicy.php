<?php

namespace App\Policies\Api\v1;

use App\Models\AttendeeImportError;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for AttendeeImportError resources.
 *
 * Import errors are produced when a device pushes user data that
 * cannot be cleanly imported. They are device-scoped.
 *
 * Permissions used:
 *  - ResolveImportErrors → list / view / resolve / ignore
 */
class AttendeeImportErrorPolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allowIf(
            $user->canResolveImportErrors(),
            'You are not allowed to view attendee import errors.',
        );
    }

    public function view(User $user, AttendeeImportError $attendeeImportError): Response
    {
        if (! $user->canResolveImportErrors()) {
            return Response::deny('You are not allowed to view attendee import errors.');
        }

        return $this->authorizeDevice($user, $attendeeImportError, hide: true);
    }

    public function resolve(User $user, AttendeeImportError $attendeeImportError): Response
    {
        if (! $user->canResolveImportErrors()) {
            return Response::deny('You are not allowed to resolve attendee import errors.');
        }

        return $this->authorizeDevice($user, $attendeeImportError, hide: false);
    }

    public function ignore(User $user, AttendeeImportError $attendeeImportError): Response
    {
        if (! $user->canResolveImportErrors()) {
            return Response::deny('You are not allowed to ignore attendee import errors.');
        }

        return $this->authorizeDevice($user, $attendeeImportError, hide: false);
    }

    public function resolveAny(User $user): Response
    {
        return $this->allowIf(
            $user->canResolveImportErrors(),
            'You are not allowed to view attendee import errors.',
        );
    }
}
