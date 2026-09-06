<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Models\User;
use App\Services\Directory\DirectoryService;
use App\Services\Sync\ContactSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncUserContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public int $timeout = 900;

    public function __construct(public int $userId)
    {
    }

    public function handle(DirectoryService $directoryService, ContactSyncService $contactSyncService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            Log::warning("SyncUserContactsJob: utilisateur #{$this->userId} introuvable, job ignoré.");

            return;
        }

        if (! $user->hasValidMicrosoftToken()) {
            Log::warning("SyncUserContactsJob: utilisateur #{$this->userId} ({$user->email}) sans refresh_token valide, job ignoré.");

            return;
        }

        $lock = Cache::lock("sync-user-{$this->userId}", 900);

        if (! $lock->get()) {
            Log::info("SyncUserContactsJob: synchronisation déjà en cours pour l'utilisateur #{$this->userId}, nouvelle tentative dans 15s.");
            $this->release(15);

            return;
        }

        Log::info("SyncUserContactsJob: début de la synchronisation pour l'utilisateur #{$this->userId} ({$user->email}).");
        $startedAt = microtime(true);

        try {
            $directoryService->refreshIfStale($user);
            $contactSyncService->run($user);

            $durationMs = round((microtime(true) - $startedAt) * 1000);
            Log::info("SyncUserContactsJob: synchronisation terminée pour l'utilisateur #{$this->userId} en {$durationMs}ms.");

            $user->forceFill(['last_sync_completed_at' => now()])->save();
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error("SyncUserContactsJob: échec de la synchronisation pour l'utilisateur #{$this->userId} : ".$exception?->getMessage());

        SyncLog::query()->create([
            'user_id' => $this->userId,
            'directory_user_id' => null,
            'action' => 'error',
            'message' => 'Échec de la synchronisation : '.($exception?->getMessage() ?? 'erreur inconnue'),
        ]);
    }
}
