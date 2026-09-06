<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'directory_user_id', 'action', 'message'])]
class SyncLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function directoryUser(): BelongsTo
    {
        return $this->belongsTo(DirectoryUser::class);
    }
}
