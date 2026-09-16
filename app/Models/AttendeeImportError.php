<?php

namespace App\Models;

use App\Policies\Api\v1\AttendeeImportErrorPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $device_serial
 * @property string $pin
 * @property string $name
 * @property string $card_number
 * @property string $vice_card
 * @property int $privilege
 * @property int $group_id
 * @property string $timezone
 * @property int $verification_mode
 * @property string $password
 * @property string $error_code
 * @property string $error_message
 * @property array $raw_data
 * @property string $status
 * @property string $admin_note
 * @property Carbon $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */

#[UsePolicy(AttendeeImportErrorPolicy::class)]
#[Fillable([
    'id',
    'device_serial',
    'pin',
    'name',
    'card_number',
    'vice_card',
    'privilege',
    'group_id',
    'timezone',
    'verification_mode',
    'password',
    'error_code',
    'error_message',
    'raw_data',
    'status',
    'admin_note',
    'resolved_at',
])]
class AttendeeImportError extends Model
{
    use HasUuids;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_RESOLVED = 'resolved';
    public const string STATUS_IGNORED = 'ignored';

    protected function casts(): array
    {
        return [
            'raw_data'           => 'array',
            'privilege'          => 'integer',
            'group_id'           => 'integer',
            'verification_mode'  => 'integer',
            'resolved_at'        => 'datetime',
        ];
    }
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
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function markResolved(?string $note = null): bool
    {
        return $this->update([
            'status'      => self::STATUS_RESOLVED,
            'admin_note'  => $note,
            'resolved_at' => now(),
        ]);
    }

    public function markIgnored(?string $note = null): bool
    {
        return $this->update([
            'status'      => self::STATUS_IGNORED,
            'admin_note'  => $note,
            'resolved_at' => now(),
        ]);
    }
}
