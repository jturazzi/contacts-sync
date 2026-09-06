<?php

namespace App\Services\Sync;

use App\Models\DirectoryUser;
use App\Models\SyncLog;
use App\Models\SyncSelection;
use App\Models\User;
use App\Services\Contacts\AvatarGenerator;
use App\Services\Graph\GraphClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ContactSyncService
{
    public function __construct(protected RuleMatcher $ruleMatcher)
    {
    }

    public function run(User $user): void
    {
        $client = new GraphClient($user);

        $ruleMatches = $this->ruleMatcher->matchedDirectoryUsers($user);

        foreach ($ruleMatches as $directoryUser) {
            SyncSelection::query()->updateOrCreate(
                ['user_id' => $user->id, 'directory_user_id' => $directoryUser->id],
                ['source' => 'rule', 'enabled' => true],
            );
        }

        $selections = $user->syncSelections()->with('directoryUser')->get();

        foreach ($selections as $selection) {
            $this->syncSelection($client, $user, $selection, $ruleMatches->has($selection->directory_user_id));
        }
    }

    protected function syncSelection(GraphClient $client, User $user, SyncSelection $selection, bool $ruleStillMatches): void
    {
        $directoryUser = $selection->directoryUser;

        if ($directoryUser !== null && ! $directoryUser->account_enabled) {
            $directoryUser = null;
        }

        $isTarget = $selection->enabled && $directoryUser !== null && ($selection->source === 'manual' || $ruleStillMatches);

        try {
            if ($isTarget) {
                $this->createOrUpdateContact($client, $user, $selection, $directoryUser);

                return;
            }

            $this->removeContactIfAny($client, $user, $selection);

            if ($selection->source === 'rule' && ! $ruleStillMatches) {
                $selection->delete();
            }
        } catch (Throwable $e) {
            Log::error("Sync contact échouée pour user #{$user->id} / directory_user {$selection->directory_user_id}: {$e->getMessage()}");

            $selection->forceFill(['last_error' => $e->getMessage()])->save();

            SyncLog::query()->create([
                'user_id' => $user->id,
                'directory_user_id' => $selection->directory_user_id,
                'action' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function createOrUpdateContact(GraphClient $client, User $user, SyncSelection $selection, DirectoryUser $directoryUser): void
    {
        $needsPhoto = is_null($selection->photo_synced_at);

        if ($selection->outlook_contact_id && $selection->last_hash === $directoryUser->content_hash) {
            $check = $client->get("/me/contacts/{$selection->outlook_contact_id}?\$select=id");

            if ($check->status() !== 404) {
                if ($needsPhoto) {
                    $this->syncPhoto($client, $selection, $directoryUser);
                }

                return;
            }

            $selection->outlook_contact_id = null;
        }

        $payload = $this->contactPayload($directoryUser);
        $isNewContact = false;

        if ($selection->outlook_contact_id) {
            $response = $client->patch("/me/contacts/{$selection->outlook_contact_id}", $payload);

            if ($response->status() === 404) {
                $response = $client->post('/me/contacts', $payload);
                $selection->outlook_contact_id = $response->json('id');
                $action = 'created';
                $isNewContact = true;
            } else {
                $action = 'updated';
            }
        } else {
            $response = $client->post('/me/contacts', $payload);
            $selection->outlook_contact_id = $response->json('id');
            $action = 'created';
            $isNewContact = true;
        }

        $selection->forceFill([
            'last_hash' => $directoryUser->content_hash,
            'last_synced_at' => now(),
            'last_error' => null,
            'photo_synced_at' => $isNewContact ? null : $selection->photo_synced_at,
        ])->save();

        if ($isNewContact || $needsPhoto) {
            $this->syncPhoto($client, $selection, $directoryUser);
        }

        SyncLog::query()->create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'action' => $action,
            'message' => "{$directoryUser->display_name} ({$action})",
        ]);
    }

    protected function syncPhoto(GraphClient $client, SyncSelection $selection, DirectoryUser $directoryUser): void
    {
        try {
            $photo = $client->getBinaryOrNull("/users/{$directoryUser->id}/photo/\$value")
                ?? AvatarGenerator::generate($directoryUser->display_name ?? '?');

            $client->putBinary("/me/contacts/{$selection->outlook_contact_id}/photo/\$value", $photo, 'image/jpeg');

            Storage::disk('public')->put("avatars/{$directoryUser->id}.jpg", $photo);

            $selection->forceFill(['photo_synced_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning("Échec de synchronisation de la photo pour le contact {$selection->outlook_contact_id} (directory_user {$directoryUser->id}): {$e->getMessage()}");
        }
    }

    protected function removeContactIfAny(GraphClient $client, User $user, SyncSelection $selection): void
    {
        if (! $selection->outlook_contact_id) {
            return;
        }

        $client->delete("/me/contacts/{$selection->outlook_contact_id}");

        SyncLog::query()->create([
            'user_id' => $user->id,
            'directory_user_id' => $selection->directory_user_id,
            'action' => 'deleted',
            'message' => 'Contact supprimé (hors périmètre de synchronisation)',
        ]);

        $selection->forceFill([
            'outlook_contact_id' => null,
            'last_hash' => null,
            'last_synced_at' => null,
            'last_error' => null,
            'photo_synced_at' => null,
        ])->save();
    }

    protected function contactPayload(DirectoryUser $directoryUser): array
    {
        return array_filter([
            'givenName' => $directoryUser->given_name,
            'surname' => $directoryUser->surname,
            'displayName' => $directoryUser->display_name,
            'jobTitle' => $directoryUser->job_title,
            'department' => $directoryUser->department,
            'companyName' => $directoryUser->company_name,
            'mobilePhone' => $directoryUser->mobile_phone,
            'businessPhones' => $directoryUser->business_phone ? [$directoryUser->business_phone] : [],
        ], fn ($value) => ! is_null($value) && $value !== []);
    }
}
