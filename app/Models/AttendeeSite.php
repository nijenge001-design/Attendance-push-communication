<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'site_id',
    'attendee_id',
    'is_active',
    'granted_at',
    'revoked_at',
    'meta',
])]
class AttendeeSite extends Pivot
{
    use HasUuids;

    protected $table = 'attendee_site';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
