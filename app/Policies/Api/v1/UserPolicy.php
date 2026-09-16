<?php

namespace App\Policies\Api\v1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for User resources.
 *
 * Special rules
 * -------------
 * - Admins can do almost everything, but they cannot lock themselves
 *   out (delete their own account, rewrite their own site assignments
 *   or permission overrides). This is enforced in before() by returning
 *   null so the ability method runs and returns the specific deny message.
 *
 * - Only admins may create other admins or assign the admin role.
 *
 * Permissions used:
 *  - ManageUsers → list / create / update / delete / manage sites & permissions
 *
 * Users may always view and update their own profile (limited fields).
 */
class UserPolicy extends Policy
{
    /**
     * Admin bypass with self-protection.
     *
     * Returning null (not false) lets the ability method run so the
     * caller receives the specific deny message.
     */
    public function before(User $user, string $ability, mixed ...$arguments): bool|null
    {
        $target = $arguments[0] ?? null;

        if (
            $target instanceof User
            && $user->is($target)
            && in_array($ability, ['delete', 'manageSites', 'managePermissions', 'resetPassword'], true)
        ) {
            return null;
        }

        return parent::before($user, $ability, ...$arguments);
    }

    public function viewAny(User $actor): Response
    {
        return $this->allowIf(
            $actor->canManageUsers(),
            'You are not allowed to list users.',
        );
    }

    public function view(User $actor, User $user): Response
    {
        return $this->allowIf(
            $actor->is($user) || $actor->canManageUsers(),
            'You are not allowed to view this user.',
        );
    }

    public function create(User $actor, UserRole|string|null $role = null): Response
    {
        if (! $actor->canManageUsers()) {
            return Response::deny('You are not allowed to create users.');
        }

        if ($this->isAdminRole($role) && ! $actor->isAdmin()) {
            return Response::deny('Only administrators can create admin users.');
        }

        return $this->allow();
    }

    public function update(User $actor, User $user, UserRole|string|null $role = null): Response
    {
        if (! $actor->is($user) && ! $actor->canManageUsers()) {
            return Response::deny('You are not allowed to update this user.');
        }

        if ($this->isAdminRole($role) && ! $actor->isAdmin()) {
            return Response::deny('Only administrators can assign the admin role.');
        }

        return $this->allow();
    }

    public function delete(User $actor, User $user): Response
    {
        if ($actor->is($user)) {
            return Response::deny('You cannot delete your own account.');
        }

        return $this->allowIf(
            $actor->canManageUsers(),
            'You are not allowed to delete users.',
        );
    }

    public function manageSites(User $actor, User $user): Response
    {
        if ($actor->is($user)) {
            return Response::deny('You cannot manage your own site assignments.');
        }

        return $this->allowIf(
            $actor->canManageUsers(),
            'You are not allowed to manage user sites.',
        );
    }

    public function managePermissions(User $actor, User $user): Response
    {
        if ($actor->is($user)) {
            return Response::deny('You cannot manage your own permission overrides.');
        }

        return $this->allowIf(
            $actor->canManageUsers(),
            'You are not allowed to manage user permissions.',
        );
    }

    public function resetPassword(User $actor, User $user): Response
    {
        if ($actor->is($user)) {
            return Response::deny('Use change-password to update your own password.');
        }

        return $this->allowIf(
            $actor->canManageUsers(),
            'You are not allowed to reset this user\'s password.',
        );
    }

    protected function isAdminRole(UserRole|string|null $role): bool
    {
        if ($role instanceof UserRole) {
            return $role === UserRole::Admin;
        }

        return $role === UserRole::Admin->value;
    }
}
