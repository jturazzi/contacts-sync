<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'directory_user_id',
    'source',
    'enabled',
    'outlook_contact_id',
    'last_hash',
    'last_synced_at',
    'last_error',
    'photo_synced_at',
])]
class SyncSelection extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'photo_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DirectoryUser, $this>
     */
    public function directoryUser(): BelongsTo
    {
        return $this->belongsTo(DirectoryUser::class);
    }
}
