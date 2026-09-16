<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'permission', 'granted'])]
class UserPermission extends Model
{
    use HasUuids;

    protected $table = 'user_permissions';

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'permission' => Permission::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
