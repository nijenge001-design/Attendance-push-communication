<?php

namespace App\Models;

use App\Policies\Api\v1\AttendeePolicy;
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
 * @property string $pin
 * @property string|null $name
 * @property int $privilege
 * @property string|null $password
 * @property string|null $card_number
 * @property string|null $vice_card
 * @property int $group_id
 * @property string|null $timezone
 * @property int $verification_mode
 */

#[UsePolicy(AttendeePolicy::class)]
#[Fillable(['pin', 'name', 'privilege', 'password', 'card_number', 'vice_card', 'group_id', 'timezone', 'verification_mode',])]
class Attendee extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'privilege' => 'integer',
            'group_id' => 'integer',
            'verification_mode' => 'integer',
        ];
    }

    // =====================================================================
    // Relationships
    // =====================================================================

    /**
     * Devices / sites this attendee has access to (many-to-many).
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(
            Device::class,
            'attendee_device',
            'attendee_id',      // pivot FK to attendees
            'device_serial',    // pivot FK to devices
            'id',               // attendees PK
            'serial_number'     // devices unique key
        )
            ->using(AttendeeDevice::class)
            ->withPivot(['privilege', 'group_id', 'timezone', 'active'])
            ->withTimestamps();
    }


    /**
     * Sites this attendee belongs to (many-to-many).
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(
            Site::class,
            'attendee_site',
            'attendee_id',
            'site_id'
        )
            ->using(AttendeeSite::class)
            ->withPivot(['is_active', 'granted_at', 'revoked_at', 'meta'])
            ->withTimestamps();
    }

    /**
     * Attendance logs of this attendee.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'pin', 'pin');
    }

    /**
     * Bio templates of this attendee.
     */
    public function bioTemplates(): HasMany
    {
        return $this->hasMany(BioTemplate::class, 'pin', 'pin');
    }

    /**
     * Photos of this attendee.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'pin', 'pin');
    }

    // =====================================================================
    // Scopes
    // =====================================================================

    public function scopeAccessibleBy($query, User $user)
    {
        $siteIds = $user->accessibleSiteIds();

        if ($siteIds === null) {
            return $query;
        }

        return $query->where(function ($q) use ($siteIds) {
            $q->whereHas('sites', fn ($s) => $s->whereIn('sites.id', $siteIds))
                ->orWhereHas('devices', fn ($d) => $d->whereIn('site_id', $siteIds));
        });
    }
    public function scopeByPin($query, string $pin)
    {
        return $query->where('pin', $pin);
    }

    public function scopeOnDevice($query, string $serial)
    {
        return $query->whereHas('devices', function ($q) use ($serial) {
            $q->where('serial_number', $serial)
                ->where('attendee_device.active', true);
        });
    }

    public function scopeActiveOnDevice($query, string $serial)
    {
        return $this->scopeOnDevice($query, $serial);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Attach this attendee to a device (site).
     */
    public function grantAccessToDevice(
        string $deviceSerial,
        array  $overrides = []
    ): void
    {
        $this->devices()->syncWithoutDetaching([
            $deviceSerial => array_merge([
                'privilege' => $this->privilege,
                'group_id' => $this->group_id,
                'timezone' => $this->timezone,
                'active' => true,
            ], $overrides),
        ]);
    }

    /**
     * Revoke access from a device.
     */
    public function revokeAccessFromDevice(string $deviceSerial): void
    {
        $this->devices()->detach($deviceSerial);
    }

    /**
     * Check if attendee has access to a specific device.
     */
    public function hasAccessToDevice(string $deviceSerial): bool
    {
        return $this->devices()
            ->where('serial_number', $deviceSerial)
            ->wherePivot('active', true)
            ->exists();
    }


    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = $this->nullIfEmpty($value);
    }

    public function setCardNumberAttribute($value): void
    {
        $this->attributes['card_number'] = $this->nullIfEmpty($value);
    }

    public function setViceCardAttribute($value): void
    {
        $this->attributes['vice_card'] = $this->nullIfEmpty($value);
    }

    private function nullIfEmpty($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
