<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id',
    'display_name',
    'given_name',
    'surname',
    'mail',
    'mobile_phone',
    'business_phone',
    'job_title',
    'department',
    'office_location',
    'account_enabled',
    'company_name',
    'content_hash',
])]
class DirectoryUser extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'account_enabled' => 'boolean',
        ];
    }

    public const SYNCED_FIELDS = [
        'given_name',
        'surname',
        'display_name',
        'job_title',
        'department',
        'mobile_phone',
        'business_phone',
        'company_name',
    ];

    public static function computeHash(array $attributes): string
    {
        $relevant = collect(self::SYNCED_FIELDS)
            ->map(fn (string $field) => (string) ($attributes[$field] ?? ''))
            ->implode('|');

        return hash('sha256', $relevant);
    }

    /**
     * @return HasMany<SyncSelection, $this>
     */
    public function syncSelections(): HasMany
    {
        return $this->hasMany(SyncSelection::class);
    }

    public static function distinctDepartments()
    {
        return static::query()->where('account_enabled', true)->whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department');
    }

    public static function distinctJobTitles()
    {
        return static::query()->where('account_enabled', true)->whereNotNull('job_title')->where('job_title', '!=', '')->distinct()->orderBy('job_title')->pluck('job_title');
    }
}
