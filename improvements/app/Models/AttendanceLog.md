```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Attendance punch record (ATTLOG table from device).
 *
 * Protocol format (2024):
 * Pin HT Time HT Status HT Verify HT Workcode HT Reserved1 HT Reserved2
 *     HT MaskFlag HT Temperature HT ConvTemperature
 *
 * ID-card variant also carries IDNum + Type.
 *
 * @property string      $id
 * @property string      $device_serial
 * @property string      $pin
 * @property Carbon      $timestamp
 * @property int         $status
 * @property int         $verify_mode
 * @property string|null $workcode
 * @property string|null $reserved1
 * @property string|null $reserved2
 * @property string|null $id_number
 * @property int         $type
 * @property int|null    $mask_flag
 * @property float|null  $temperature
 * @property float|null  $conv_temperature
 */
#[Fillable([
    'device_serial',
    'pin',
    'timestamp',
    'status',
    'verify_mode',
    'workcode',
    'reserved1',
    'reserved2',
    'id_number',
    'type',
    'mask_flag',
    'temperature',
    'conv_temperature',
])]
class AttendanceLog extends Model
{
    use HasUuids, SoftDeletes;

    // Common status values (device-dependent, 0 = check-in on most firmwares)
    public const int STATUS_CHECK_IN = 0;
    public const int STATUS_CHECK_OUT = 1;
    public const int STATUS_BREAK_OUT = 2;
    public const int STATUS_BREAK_IN = 3;
    public const int STATUS_OT_IN = 4;
    public const int STATUS_OT_OUT = 5;

    // Record type (ID-card / verification variant)
    public const int TYPE_ATTENDANCE = 0;
    public const int TYPE_VERIFICATION = 1;

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'status' => 'integer',
            'verify_mode' => 'integer',
            'type' => 'integer',
            'mask_flag' => 'integer',
            'temperature' => 'float',
            'conv_temperature' => 'float',
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

    public function scopeForDevice(Builder $query, string $serial): Builder
    {
        return $query->where('device_serial', $serial);
    }

    public function scopeForPin(Builder $query, string $pin): Builder
    {
        return $query->where('pin', $pin);
    }

    public function scopeBetweenDates(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('timestamp', [$start, $end]);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('timestamp', today());
    }

    public function scopeWithMask(Builder $query): Builder
    {
        return $query->where('mask_flag', 1);
    }

    public function scopeHasTemperature(Builder $query): Builder
    {
        return $query->whereNotNull('temperature');
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    public function woreMask(): bool
    {
        return $this->mask_flag === 1;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CHECK_IN => 'Check In',
            self::STATUS_CHECK_OUT => 'Check Out',
            self::STATUS_BREAK_OUT => 'Break Out',
            self::STATUS_BREAK_IN => 'Break In',
            self::STATUS_OT_IN => 'OT In',
            self::STATUS_OT_OUT => 'OT Out',
            default => "Status {$this->status}",
        };
    }
}
```
