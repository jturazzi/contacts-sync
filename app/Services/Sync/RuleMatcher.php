<?php

namespace App\Services\Sync;

use App\Models\DirectoryUser;
use App\Models\SyncRule;
use App\Models\User;
use Illuminate\Support\Collection;

class RuleMatcher
{
    /**
     * @return Collection<string, DirectoryUser>
     */
    public function matchedDirectoryUsers(User $user): Collection
    {
        if ($user->sync_all_enabled) {
            return DirectoryUser::query()->where('account_enabled', true)->get()->keyBy('id');
        }

        $rules = $user->syncRules()->where('enabled', true)->get();

        if ($rules->isEmpty()) {
            return collect();
        }

        return DirectoryUser::query()
            ->where('account_enabled', true)
            ->get()
            ->filter(fn (DirectoryUser $directoryUser) => $rules->contains(
                fn (SyncRule $rule) => $rule->matches($directoryUser)
            ))
            ->keyBy('id');
    }

    /**
     * @return Collection<int, DirectoryUser>
     */
    public function matchingDirectoryUsers(SyncRule $rule): Collection
    {
        return DirectoryUser::query()
            ->where('account_enabled', true)
            ->get()
            ->filter(fn (DirectoryUser $directoryUser) => $rule->matches($directoryUser))
            ->sortBy('display_name')
            ->values();
    }

    public function previewCount(SyncRule $rule): int
    {
        return $this->matchingDirectoryUsers($rule)->count();
    }
}
