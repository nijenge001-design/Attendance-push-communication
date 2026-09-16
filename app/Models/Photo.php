<?php

namespace App\Models;

use App\Policies\Api\v1\PhotoPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $device_serial
 * @property string|null $pin
 * @property string $filename
 * @property int|null $type
 * @property string|null $content
 * @property string|null $url
 */

#[UsePolicy(PhotoPolicy::class)]
#[Fillable([
    'device_serial',
    'pin',
    'filename',
    'type',
    'content',
    'url',
])]
class Photo extends Model
{
    use HasUuids, SoftDeletes;

    // Common photo types (aligned with protocol)
    public const int TYPE_COMMON = 0;
    public const int TYPE_FINGER = 1;
    public const int TYPE_FACE = 2;
    public const int TYPE_VOICE = 3;
    public const int TYPE_IRIS = 4;
    public const int TYPE_RETINA = 5;
    public const int TYPE_PALM = 6;
    public const int TYPE_FINGER_VEIN = 7;
    public const int TYPE_PALM_VEIN = 8;
    public const int TYPE_VISIBLE_FACE = 9;
    public const int TYPE_ATTENDANCE = 10; // custom – attendance photo

    protected function casts(): array
    {
        return [
            'type' => 'integer',
        ];
    }

    // =====================================================================
    // Relationships
    // =====================================================================

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_serial', 'serial_number');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'pin', 'pin');
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

        return $query->whereIn(
            'device_serial',
            Device::whereIn('site_id', $siteIds)->select('serial_number')
        );
    }
    public function scopeForDevice($query, string $serial)
    {
        return $query->where('device_serial', $serial);
    }

    public function scopeForPin($query, string $pin)
    {
        return $query->where('pin', $pin);
    }

    public function scopeOfType($query, int $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAttendance($query)
    {
        return $query->where('type', self::TYPE_ATTENDANCE);
    }

    public function scopeFace($query)
    {
        return $query->whereIn('type', [
            self::TYPE_FACE,
            self::TYPE_VISIBLE_FACE,
        ]);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_COMMON => 'Common',
            self::TYPE_FINGER => 'Fingerprint',
            self::TYPE_FACE => 'Face',
            self::TYPE_VOICE => 'Voice',
            self::TYPE_IRIS => 'Iris',
            self::TYPE_RETINA => 'Retina',
            self::TYPE_PALM => 'Palm',
            self::TYPE_FINGER_VEIN => 'Finger Vein',
            self::TYPE_PALM_VEIN => 'Palm Vein',
            self::TYPE_VISIBLE_FACE => 'Visible Face',
            self::TYPE_ATTENDANCE => 'Attendance Photo',
            default => 'Unknown',
        };
    }

    /**
     * Get the raw binary content (decoded from base64).
     */
    public function getBinaryContent(): ?string
    {
        return $this->content ? base64_decode($this->content) : null;
    }

    /**
     * Check if the photo has binary content stored.
     */
    public function hasContent(): bool
    {
        return !empty($this->content);
    }
}
