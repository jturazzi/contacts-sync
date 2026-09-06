<?php

namespace App\Services\Directory;

use App\Models\DirectoryUser;
use App\Models\User;
use App\Services\Graph\GraphClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DirectoryService
{
    protected const SELECT_FIELDS = 'id,displayName,givenName,surname,mail,mobilePhone,businessPhones,jobTitle,department,officeLocation,accountEnabled,companyName';

    public function refreshIfStale(User $user): void
    {
        $lock = Cache::lock('directory:refresh', 900);

        if (! $lock->get()) {
            return;
        }

        try {
            $lastRefresh = Cache::get('directory:last_refreshed_at');
            $interval = (int) config('graph.directory_refresh_interval_minutes');

            if ($lastRefresh && (time() - $lastRefresh) < $interval * 60) {
                return;
            }

            $this->refresh($user);
            Cache::put('directory:last_refreshed_at', now()->timestamp, now()->addDay());
        } finally {
            $lock->release();
        }
    }

    public function refresh(User $user): void
    {
        $client = new GraphClient($user);

        $path = '/users?$select='.self::SELECT_FIELDS.'&$top=999';
        $seenIds = [];
        $skipped = 0;

        do {
            $response = $client->get($this->isFullUrl($path) ? $this->stripBaseUrl($path) : $path);
            $payload = $response->json();

            foreach ($payload['value'] ?? [] as $entry) {
                if ($this->upsert($entry)) {
                    $seenIds[] = $entry['id'];
                } else {
                    $skipped++;
                }
            }

            $path = $payload['@odata.nextLink'] ?? null;
        } while ($path);

        $removed = DirectoryUser::query()->whereNotIn('id', $seenIds)->delete();

        $this->cleanupOrphanedAvatars();

        Log::info('Annuaire M365 rafraîchi : '.count($seenIds)." personnes conservées, {$skipped} ignorées (sans téléphone mobile ni professionnel), {$removed} retirées.");
    }

    protected function cleanupOrphanedAvatars(): void
    {
        $activeIds = DirectoryUser::query()->where('account_enabled', true)->pluck('id')->flip();

        foreach (Storage::disk('public')->files('avatars') as $path) {
            if (! isset($activeIds[pathinfo($path, PATHINFO_FILENAME)])) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    protected function upsert(array $entry): bool
    {
        $businessPhone = $entry['businessPhones'][0] ?? null;
        $mobilePhone = $entry['mobilePhone'] ?? null;

        if (blank($mobilePhone) && blank($businessPhone)) {
            return false;
        }

        $attributes = [
            'display_name' => $entry['displayName'] ?? null,
            'given_name' => $entry['givenName'] ?? null,
            'surname' => $entry['surname'] ?? null,
            'mail' => $entry['mail'] ?? null,
            'mobile_phone' => $mobilePhone,
            'business_phone' => $businessPhone,
            'job_title' => $entry['jobTitle'] ?? null,
            'department' => $entry['department'] ?? null,
            'office_location' => $entry['officeLocation'] ?? null,
            'account_enabled' => $entry['accountEnabled'] ?? true,
            'company_name' => $entry['companyName'] ?? null,
        ];

        $attributes['content_hash'] = DirectoryUser::computeHash($attributes);

        $directoryUser = DirectoryUser::withTrashed()->updateOrCreate(['id' => $entry['id']], $attributes);

        if ($directoryUser->trashed()) {
            $directoryUser->restore();
        }

        return true;
    }

    protected function isFullUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    protected function stripBaseUrl(string $url): string
    {
        return str_replace(rtrim(config('graph.base_url'), '/'), '', $url);
    }
}
