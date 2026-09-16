<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/*
 *
 */
#[Fillable([
    'attendee_id',
    'device_serial',
    'privilege',
    'group_id',
    'timezone',
    'active',
])]
class AttendeeDevice extends Pivot
{
    use HasUuids;

    protected $table = 'attendee_device';


    protected function casts(): array
    {
        return [
            'privilege' => 'integer',
            'group_id'  => 'integer',
            'active'    => 'boolean',
        ];
    }
}
