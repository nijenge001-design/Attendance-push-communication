<?php

namespace App\Models;

use App\Policies\Api\v1\SitePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $timezone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property bool $is_active
 * @property array|null $meta
 */

#[UsePolicy(SitePolicy::class)]
#[Fillable(['code', 'name', 'timezone', 'address', 'city', 'country', 'is_active', 'meta'])]
class Site extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    // =====================================================================
    // Relationships
    // =====================================================================

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(
            Attendee::class,
            'attendee_site',
            'site_id',
            'attendee_id'
        )
            ->using(AttendeeSite::class)
            ->withPivot(['is_active', 'granted_at', 'revoked_at', 'meta'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_site')
            ->using(SiteUser::class)
            ->withPivot(['is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    // =====================================================================
    // Scopes
    // =====================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Restrict to sites the given user can access.
     * Admins are unrestricted.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $siteIds = $user->accessibleSiteIds();

        if ($siteIds === null) {
            return $query; // admin – all sites
        }

        return $query->whereIn('id', $siteIds);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
