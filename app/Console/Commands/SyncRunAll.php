<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserContactsJob;
use App\Models\User;
use Illuminate\Console\Command;

class SyncRunAll extends Command
{
    protected $signature = 'sync:run-all';

    protected $description = "Dispatche une synchronisation des contacts Outlook pour chaque utilisateur ayant une sélection active, une règle active, ou 'tout synchroniser' activé";

    public function handle(): int
    {
        $users = User::query()
            ->whereNotNull('refresh_token')
            ->where(function ($query) {
                $query->whereHas('syncSelections', fn ($q) => $q->where('enabled', true))
                    ->orWhereHas('syncRules', fn ($q) => $q->where('enabled', true))
                    ->orWhere('sync_all_enabled', true);
            })
            ->get();

        foreach ($users as $user) {
            SyncUserContactsJob::dispatch($user->id);
        }

        $this->info("{$users->count()} synchronisation(s) dispatchée(s).");

        return self::SUCCESS;
    }
}
