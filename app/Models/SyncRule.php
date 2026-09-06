<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'department',
    'job_title',
    'match_type',
    'enabled',
])]
class SyncRule extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(DirectoryUser $directoryUser): bool
    {
        if (blank($this->department) && blank($this->job_title)) {
            return false;
        }

        if (filled($this->department) && ! $this->fieldMatches($directoryUser->department, $this->department)) {
            return false;
        }

        if (filled($this->job_title) && ! $this->fieldMatches($directoryUser->job_title, $this->job_title)) {
            return false;
        }

        return true;
    }

    protected function fieldMatches(?string $value, string $needle): bool
    {
        if (blank($value)) {
            return false;
        }

        if ($this->match_type === 'exact') {
            return mb_strtolower($value) === mb_strtolower($needle);
        }

        return str_contains(mb_strtolower($value), mb_strtolower($needle));
    }
}
