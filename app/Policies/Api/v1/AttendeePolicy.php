<?php

namespace App\Policies\Api\v1;

use App\Models\Attendee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for Attendee (employee) resources.
 *
 * Multi-tenancy model
 * -------------------
 * Attendees can be linked to Sites directly and/or to Devices.
 * A user may see/manage an attendee when they share at least one
 * site (via the attendee’s sites or via devices that belong to
 * sites the user can access).
 *
 * Permissions used:
 *  - ReadEmployees   → list / view
 *  - ManageEmployees → create / update
 *  - DeleteEmployees → delete
 *  - ManageDevices   → grant / revoke device access
 */
class AttendeePolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allowIf(
            $user->canReadEmployees(),
            'You are not allowed to view attendees.',
        );
    }

    public function view(User $user, Attendee $attendee): Response
    {
        if (! $user->canReadEmployees()) {
            return Response::deny('You are not allowed to view attendees.');
        }

        return $this->sharesSiteWith($user, $attendee, hide: true);
    }

    public function create(User $user): Response
    {
        return $this->allowIf(
            $user->canManageEmployees(),
            'You are not allowed to create attendees.',
        );
    }

    public function update(User $user, Attendee $attendee): Response
    {
        if (! $user->canManageEmployees()) {
            return Response::deny('You are not allowed to update attendees.');
        }

        return $this->sharesSiteWith($user, $attendee, hide: false);
    }

    public function delete(User $user, Attendee $attendee): Response
    {
        if (! $user->canDeleteEmployees()) {
            return Response::deny('You are not allowed to delete attendees.');
        }

        return $this->sharesSiteWith($user, $attendee, hide: false);
    }

    public function manageAccess(User $user, Attendee $attendee): Response
    {
        if (! $user->canManageDevices()) {
            return Response::deny('You are not allowed to manage attendee device access.');
        }

        return $this->sharesSiteWith($user, $attendee, hide: false);
    }

    public function manageAccessAny(User $user): Response
    {
        return $this->allowIf(
            $user->canManageDevices(),
            'You are not allowed to manage attendee device access.',
        );
    }

    /**
     * @param  bool  $hide  true = 404 (reads), false = 403 (writes)
     */
    protected function sharesSiteWith(User $user, Attendee $attendee, bool $hide = true): Response
    {
        $userSiteIds = $user->accessibleSiteIds();

        // Admin → accessibleSiteIds() returns null → unrestricted.
        // (Admins normally never reach here because Policy::before() allows them.)
        if ($userSiteIds === null) {
            return $this->allow();
        }

        if ($userSiteIds === []) {
            return $this->denyAccess($hide, 'You do not have access to this attendee.');
        }

        $shares = $attendee->sites()->whereIn('sites.id', $userSiteIds)->exists()
            || $attendee->devices()->whereIn('site_id', $userSiteIds)->exists();

        return $shares
            ? $this->allow()
            : $this->denyAccess($hide, 'You do not have access to this attendee.');
    }
}
