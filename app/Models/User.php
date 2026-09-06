<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'ms_id', 'tenant_id', 'avatar', 'access_token', 'refresh_token', 'token_expires_at', 'microsoft_disconnected_at', 'sync_all_enabled', 'locale'])]
#[Hidden(['password', 'remember_token', 'access_token', 'refresh_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'microsoft_disconnected_at' => 'datetime',
            'sync_all_enabled' => 'boolean',
            'last_sync_completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SyncSelection, $this>
     */
    public function syncSelections(): HasMany
    {
        return $this->hasMany(SyncSelection::class);
    }

    /**
     * @return HasMany<SyncRule, $this>
     */
    public function syncRules(): HasMany
    {
        return $this->hasMany(SyncRule::class);
    }

    /**
     * @return HasMany<SyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function hasValidMicrosoftToken(): bool
    {
        return filled($this->refresh_token) && is_null($this->microsoft_disconnected_at);
    }
}
