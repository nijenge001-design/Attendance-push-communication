<?php

namespace App\Policies\Api\v1;

use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for Site resources.
 *
 * Sites are the primary multi-tenant boundary.
 * Most other resources (devices, attendees, logs, …) inherit
 * access control from the sites a user is assigned to.
 *
 * Permissions used:
 *  - ManageSites → create / update
 *
 * Delete is reserved for administrators only.
 * viewAny is open (the controller filters to accessible sites).
 */
class SitePolicy extends Policy
{
    public function viewAny(User $user): Response
    {
        return $this->allow();
    }

    public function view(User $user, Site $site): Response
    {
        return $this->authorizeSite($user, $site->id, hide: true);
    }

    public function create(User $user): Response
    {
        return $this->allowIf(
            $user->canManageSites(),
            'You are not allowed to create sites.',
        );
    }

    public function update(User $user, Site $site): Response
    {
        if (! $user->canManageSites()) {
            return Response::deny('You are not allowed to update sites.');
        }

        return $this->authorizeSite($user, $site->id, hide: false);
    }

    public function delete(User $user, Site $site): Response
    {
        return Response::deny('Only administrators can delete sites.');
    }
}
