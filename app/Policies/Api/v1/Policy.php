<?php

namespace App\Policies\Api\v1;

use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * Base policy for all API (v1) policies.
 *
 * Design principles
 * -----------------
 * 1. Admin bypass lives here (single source of truth).
 *    Child policies should almost never re-implement admin checks.
 *
 * 2. Cross-tenant reads use denyAsNotFound() so we do not leak
 *    the existence of resources the user cannot access.
 *    Writes use a regular 403 deny (hide: false).
 *
 * 3. Permission checks use the User helpers (canManageDevices(), etc.)
 *    which already combine role defaults + per-user overrides.
 *
 * 4. Device-scoped resources resolve the Device either from the model
 *    itself, a loaded relation, or a device_serial column.
 */
abstract class Policy
{
    use HandlesAuthorization;

    /**
     * Global admin bypass.
     *
     * Returns true for admins (allow everything) or null so the
     * specific policy method is evaluated for non-admins.
     *
     * Child policies may override this for special self-protection
     * cases (see UserPolicy).
     *
     * Laravel before() semantics:
     *   true  → allow immediately, ability method is NOT called
     *   false → deny immediately, ability method is NOT called
     *   null  → run the ability method
     */
    public function before(User $user, string $ability, mixed ...$arguments): bool|null
    {
        return $user->isAdmin() ? true : null;
    }

    protected function allow(): Response
    {
        return Response::allow();
    }

    protected function allowIf(bool $condition, string $message): Response
    {
        return $condition
            ? Response::allow()
            : Response::deny($message);
    }

    /**
     * Hide existence (404) vs explicit deny (403).
     */
    protected function denyAccess(bool $hide, string $message): Response
    {
        return $hide
            ? Response::denyAsNotFound()
            : Response::deny($message);
    }

    /**
     * @param  bool  $hide  true = 404 (reads), false = 403 (writes)
     */
    protected function authorizeDevice(User $user, object|null $model, bool $hide = true): Response
    {
        $device = $this->resolveDevice($model);

        if (! $device instanceof Device) {
            return $this->denyAccess($hide, 'Device not found.');
        }

        if ($user->hasAccessToDevice($device)) {
            return Response::allow();
        }

        return $this->denyAccess($hide, 'You do not have access to this device.');
    }

    /**
     * @param  bool  $hide  true = 404 (reads), false = 403 (writes)
     */
    protected function authorizeSite(User $user, ?string $siteId, bool $hide = true): Response
    {
        if ($siteId === null || $siteId === '') {
            return Response::allow();
        }

        if ($user->hasAccessToSite($siteId)) {
            return Response::allow();
        }

        return $this->denyAccess($hide, 'You do not have access to this site.');
    }

    /**
     * Resolution order:
     *  1. The model is itself a Device
     *  2. The model has an already-loaded `device` relation
     *  3. The model has a `device_serial` attribute → lookup
     */
    protected function resolveDevice(object|null $model): ?Device
    {
        if ($model === null) {
            return null;
        }

        if ($model instanceof Device) {
            return $model;
        }

        if (
            method_exists($model, 'relationLoaded')
            && $model->relationLoaded('device')
            && $model->device instanceof Device
        ) {
            return $model->device;
        }

        $serial = data_get($model, 'device_serial');

        if (is_string($serial) && $serial !== '') {
            return Device::query()
                ->where('serial_number', $serial)
                ->first();
        }

        return null;
    }
}
