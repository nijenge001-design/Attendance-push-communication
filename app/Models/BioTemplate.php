<?php

namespace App\Models;

use App\Policies\Api\v1\BioTemplatePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $device_serial
 * @property string $pin
 * @property int $type
 * @property int $no
 * @property int $index
 * @property int $valid
 * @property int $duress
 * @property string|null $major_ver
 * @property string|null $minor_ver
 * @property string|null $format
 * @property string $template_data
 */

#[UsePolicy(BioTemplatePolicy::class)]
#[Fillable([
    'device_serial',
    'pin',
    'type',
    'no',
    'index',
    'valid',
    'duress',
    'major_ver',
    'minor_ver',
    'format',
    'template_data',
])]
class BioTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    // Biometric type indexes (Appendix 10 – Hybrid Identification Protocol)
    public const int TYPE_GENERAL       = 0;  // Common
    public const int TYPE_FINGER        = 1;  // Fingerprint
    public const int TYPE_FACE          = 2;  // Near-infrared face
    public const int TYPE_VOICE         = 3;  // Voiceprint
    public const int TYPE_IRIS          = 4;
    public const int TYPE_RETINA        = 5;
    public const int TYPE_PALM          = 6;  // Palmprint
    public const int TYPE_FINGER_VEIN   = 7;
    public const int TYPE_PALM_VEIN     = 8;
    public const int TYPE_VISIBLE_FACE  = 9;  // Visible light face
    public const int TYPE_VISIBLE_PALM  = 10; // Visible light palm (added in protocol ≥ 4.6)

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'no' => 'integer',
            'index' => 'integer',
            'valid' => 'integer',
            'duress' => 'integer',
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

    public function scopeValid($query)
    {
        return $query->where('valid', 1);
    }

    public function scopeFinger($query)
    {
        return $query->where('type', self::TYPE_FINGER);
    }

    public function scopeFace($query)
    {
        return $query->whereIn('type', [self::TYPE_FACE, self::TYPE_VISIBLE_FACE]);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    public function isValid(): bool
    {
        return $this->valid === 1;
    }

    public function isDuress(): bool
    {
        return $this->duress === 1;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_GENERAL => 'General',
            self::TYPE_FINGER => 'Fingerprint',
            self::TYPE_FACE => 'Face',
            self::TYPE_VOICE => 'Voice',
            self::TYPE_IRIS => 'Iris',
            self::TYPE_RETINA => 'Retina',
            self::TYPE_PALM => 'Palm',
            self::TYPE_FINGER_VEIN => 'Finger Vein',
            self::TYPE_PALM_VEIN => 'Palm Vein',
            self::TYPE_VISIBLE_FACE => 'Visible Face',
            default => 'Unknown',
        };
    }
}
