<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Illuminate\Console\Command;

class PruneSyncLogs extends Command
{
    protected $signature = 'sync:prune-logs';

    protected $description = "Supprime les entrées de l'historique de synchronisation plus vieilles que la durée de rétention configurée";

    public function handle(): int
    {
        $days = (int) config('graph.sync_log_retention_days');

        $deleted = SyncLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$deleted} entrée(s) d'historique supprimée(s) (plus vieilles que {$days} jours).");

        return self::SUCCESS;
    }
}
