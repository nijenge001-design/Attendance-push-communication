<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Jobs\DispatchMailJob;
use App\Jobs\SendPasswordChangedEmail;
use App\Jobs\SendResetPasswordEmail;
use App\Policies\Api\v1\UserPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property string $phone_number
 * @property string $password
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $password_changed_at
 * @property UserRole $role
 */
#[UsePolicy(UserPolicy::class)]
#[Fillable([
    'name',
    'username',
    'email',
    'phone_number',
    'password',
    'role',
    'phone_verified_at',
    'email_verified_at',
    'password_changed_at',
])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    // =====================================================================
    // Relationships
    // =====================================================================

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'user_site')
            ->using(SiteUser::class)
            ->withPivot(['is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    public function allSites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'user_site')
            ->using(SiteUser::class)
            ->withPivot(['is_active'])
            ->withTimestamps();
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    // =====================================================================
    // Site scope
    // =====================================================================

    // User model
    public function scopeAccessibleBy($query, User $actor)
    {
        if ($actor->isAdmin()) {
            return $query;
        }

        $siteIds = $actor->accessibleSiteIds() ?? [];

        return $query->whereHas('sites', fn ($q) => $q->whereIn('sites.id', $siteIds));
    }
    /**
     * @return list<string>|null  null = unrestricted (admin)
     */
    public function accessibleSiteIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return $this->sites()->pluck('sites.id')->all();
    }

    public function hasAccessToSite(string $siteId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->sites()->where('sites.id', $siteId)->exists();
    }

    public function hasAccessToDevice(Device $device): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$device->site_id) {
            return false;
        }

        return $this->hasAccessToSite($device->site_id);
    }

    // =====================================================================
    // Permissions (role defaults + per-user overrides)
    // =====================================================================

    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $key = $permission instanceof Permission ? $permission->value : $permission;

        $overrides = $this->permissionOverrideMap();

        if (array_key_exists($key, $overrides)) {
            return (bool)$overrides[$key];
        }

        if (!$this->role instanceof UserRole) {
            return false;
        }

        foreach (Permission::defaultsFor($this->role) as $perm) {
            if ($perm->value === $key) {
                return true;
            }
        }

        return false;
    }

    public function grantPermission(Permission|string $permission): void
    {
        $this->setPermissionOverride($permission, true);
    }

    public function denyPermission(Permission|string $permission): void
    {
        $this->setPermissionOverride($permission, false);
    }

    public function clearPermissionOverride(Permission|string $permission): void
    {
        $key = $permission instanceof Permission ? $permission->value : $permission;

        UserPermission::where('user_id', $this->id)
            ->where('permission', $key)
            ->delete();

        $this->forgetPermissionCache();
    }

    protected function setPermissionOverride(Permission|string $permission, bool $granted): void
    {
        $key = $permission instanceof Permission ? $permission->value : $permission;

        UserPermission::updateOrCreate(
            ['user_id' => $this->id, 'permission' => $key],
            ['granted' => $granted]
        );

        $this->forgetPermissionCache();
    }

    /** @return array<string, bool> */
    protected function permissionOverrideMap(): array
    {
        return Cache::remember(
            $this->permissionCacheKey(),
            now()->addMinutes(30),
            fn() => UserPermission::where('user_id', $this->id)
                ->pluck('granted', 'permission')
                ->map(fn($v) => (bool)$v)
                ->all()
        );
    }

    protected function permissionCacheKey(): string
    {
        return "user.{$this->id}.permission_overrides";
    }

    public function forgetPermissionCache(): void
    {
        Cache::forget($this->permissionCacheKey());
    }

    // =====================================================================
    // Role helpers
    // =====================================================================

    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    public function isManager(): bool
    {
        return $this->role?->isManager() ?? false;
    }

    public function isOperator(): bool
    {
        return $this->role?->isOperator() ?? false;
    }

    public function isViewer(): bool
    {
        return $this->role?->isViewer() ?? false;
    }

    public function isIntegration(): bool
    {
        return $this->role?->isIntegration() ?? false;
    }

    public function canManageDevices(): bool
    {
        return $this->hasPermission(Permission::ManageDevices);
    }

    public function canApproveDevices(): bool
    {
        return $this->hasPermission(Permission::ApproveDevices);
    }

    public function canManageEmployees(): bool
    {
        return $this->hasPermission(Permission::ManageEmployees);
    }

    public function canReadEmployees(): bool
    {
        return $this->hasPermission(Permission::ReadEmployees);
    }

    public function canDeleteEmployees(): bool
    {
        return $this->hasPermission(Permission::DeleteEmployees);
    }

    public function canManageSites(): bool
    {
        return $this->hasPermission(Permission::ManageSites);
    }

    public function canManageUsers(): bool
    {
        return $this->hasPermission(Permission::ManageUsers);
    }

    public function canManageCommands(): bool
    {
        return $this->hasPermission(Permission::ManageCommands);
    }

    public function canResolveImportErrors(): bool
    {
        return $this->hasPermission(Permission::ResolveImportErrors);
    }

    public function canReadAttendance(): bool
    {
        return $this->hasPermission(Permission::ReadAttendance);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        DispatchMailJob::pushAuth(new SendResetPasswordEmail($this, $token));
    }

    public function resetToPassword(#[\SensitiveParameter] string $password, bool $revokeTokens = true): void
    {
        $this->forceFill([
            'password' => $password,
            'password_changed_at' => now(),
        ])->save();

        if ($revokeTokens) {
            $this->tokens()->delete();
        }

        if (filled($this->email)) {
            DispatchMailJob::pushAuth(new SendPasswordChangedEmail($this));
        }
    }
}
