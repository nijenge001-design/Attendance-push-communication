<?php

namespace App\Models;

use App\Events\DeviceHeartbeat;
use App\Observers\DeviceObserver;
use App\Policies\Api\v1\DevicePolicy;
use App\Services\DeviceCommandQueue;
use App\Support\HybridBio;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $serial_number
 * @property string|null $device_name
 * @property string $status
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property string|null $ip
 * @property string|null $mac_address
 * @property string|null $firmware_version
 * @property string|null $push_version
 * @property string|null $platform
 * @property string|null $language
 * @property int|null $user_count
 * @property int|null $fp_count
 * @property int|null $face_count
 * @property int|null $attlog_count
 * @property bool|null $finger_fun_on
 * @property bool|null $face_fun_on
 * @property bool|null $photo_fun_on
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_heartbeat_at
 * @property string|null $last_heartbeat_source
 * @property array|null $capabilities
 * @property string|null $site_id
 */

#[UsePolicy(DevicePolicy::class)]
#[Fillable([
    'serial_number',
    'device_name',
    'status',
    'approved_at',
    'rejection_reason',
    'ip',
    'mac_address',
    'firmware_version',
    'push_version',
    'platform',
    'language',
    'user_count',
    'fp_count',
    'face_count',
    'attlog_count',
    'finger_fun_on',
    'face_fun_on',
    'photo_fun_on',
    'last_seen_at',
    'last_heartbeat_at',
    'last_heartbeat_source',
    'capabilities',
    'site_id',
])]
#[ObservedBy([DeviceObserver::class])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_APPROVED = 'approved';
    public const string STATUS_BLOCKED = 'blocked';

    protected static function newFactory(): DeviceFactory
    {
        return DeviceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'capabilities' => 'array',
            'last_seen_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'finger_fun_on' => 'boolean',
            'face_fun_on' => 'boolean',
            'photo_fun_on' => 'boolean',
        ];
    }

    /**
     * Resolve {device} from the URL by UUID *or* serial number.
     *
     * Examples that both work:
     *   /api/v1/devices/9f3c2a1b-....-....
     *   /api/v1/devices/PSS7234900035
     *
     * UUID is tried first when the value looks like a UUID; otherwise
     * (and as fallback) we look up by serial_number.
     */
    public function resolveRouteBinding($value, $field = null): Model|Device|null
    {
        // Explicit field from route (e.g. {device:serial_number}) still wins.
        if ($field !== null) {
            return $this->where($field, $value)->firstOrFail();
        }

        $query = static::query();

        // Looks like a UUID → try primary key first, then serial as fallback.
        if (is_string($value) && preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $value
            )) {
            $device = (clone $query)->where($this->getKeyName(), $value)->first();
            if ($device) {
                return $device;
            }
        }

        // Serial number (or non-UUID string).
        return $query->where('serial_number', $value)->firstOrFail();
    }

    // =====================================================================
    // Relationships
    // =====================================================================

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Attendees who have access to this device (many-to-many).
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(
            Attendee::class,
            'attendee_device',
            'device_serial',
            'attendee_id',
            'serial_number',
            'id'
        )
            ->using(AttendeeDevice::class)
            ->withPivot(['privilege', 'group_id', 'timezone', 'active'])
            ->withTimestamps();
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'device_serial', 'serial_number');
    }

    public function bioTemplates(): HasMany
    {
        return $this->hasMany(BioTemplate::class, 'device_serial', 'serial_number');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'device_serial', 'serial_number');
    }

    public function pendingCommands(): HasMany
    {
        return $this->hasMany(PendingCommand::class, 'device_serial', 'serial_number');
    }

    // =====================================================================
    // Scopes
    // =====================================================================
    public function scopeAccessibleBy($query, User $user)
    {
        $siteIds = $user->accessibleSiteIds();
        if ($siteIds === null) return $query;

        return $query->whereIn('site_id', $siteIds);
    }
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    public function scopeOnline($query, int $thresholdMinutes = 5)
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes($thresholdMinutes));
    }

    public function scopeOffline($query, int $thresholdMinutes = 5)
    {
        return $query->where(function ($q) use ($thresholdMinutes) {
            $q->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subMinutes($thresholdMinutes));
        });
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Record a heartbeat from the device.
     */
    public function heartbeat(string $source = 'ping', ?string $ip = null): void
    {
        $data = [
            'last_seen_at' => now(),
            'last_heartbeat_at' => now(),
            'last_heartbeat_source' => $source,
        ];

        if ($ip) {
            $data['ip'] = $ip;
        }

        $this->update($data);
        event(new DeviceHeartbeat($this, $source));
    }

    /**
     * Check if device is considered online.
     */
    public function isOnline(int $thresholdMinutes = 5): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gt(now()->subMinutes($thresholdMinutes));
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function approve(): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function block(?string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_BLOCKED,
            'rejection_reason' => $reason,
        ]);
    }

    public function reject(?string $reason = null): bool
    {
        return $this->block($reason);
    }

    /**
     * Queue a command for this device.
     */
    public function queueCommand(string $commandText): PendingCommand
    {
        return app(DeviceCommandQueue::class)->queueForDevice($this, $commandText);
    }

    /**
     * Queue multiple commands for this device.
     */
    public function queueCommands(array $commands): \Illuminate\Support\Collection
    {
        return app(DeviceCommandQueue::class)->queueMany($this->serial_number, $commands);
    }

    // =====================================================================
    // Hybrid Identification Protocol helpers
    // =====================================================================

    /**
     * Device-reported MultiBioDataSupport mask (templates), if any.
     */
    public function multiBioDataSupport(): ?string
    {
        $v = $this->capabilities['multi_bio_data_support'] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Device-reported MultiBioPhotoSupport mask (comparison photos), if any.
     */
    public function multiBioPhotoSupport(): ?string
    {
        $v = $this->capabilities['multi_bio_photo_support'] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Effective (server ∩ device) template types this device can use.
     *
     * @return list<int>
     */
    public function supportedBioDataTypes(): array
    {
        $server = (string) config('iclock.multi_bio_data_support', HybridBio::defaultDataSupport());
        $device = $this->multiBioDataSupport() ?? $server;

        return HybridBio::enabledTypes(
            HybridBio::intersect($server, $device)
        );
    }

    /**
     * Effective (server ∩ device) comparison-photo types this device can use.
     *
     * @return list<int>
     */
    public function supportedBioPhotoTypes(): array
    {
        $server = (string) config('iclock.multi_bio_photo_support', HybridBio::defaultPhotoSupport());
        $device = $this->multiBioPhotoSupport() ?? $server;

        return HybridBio::enabledTypes(
            HybridBio::intersect($server, $device)
        );
    }

    /**
     * Whether the device supports a given biometric template type under Hybrid Identification.
     */
    public function supportsBioDataType(int $type): bool
    {
        return in_array($type, $this->supportedBioDataTypes(), true);
    }

    /**
     * Whether the device supports a given comparison-photo type under Hybrid Identification.
     */
    public function supportsBioPhotoType(int $type): bool
    {
        return in_array($type, $this->supportedBioPhotoTypes(), true);
    }
}
