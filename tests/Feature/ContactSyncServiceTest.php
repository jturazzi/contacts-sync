<?php

namespace Tests\Feature;

use App\Models\DirectoryUser;
use App\Models\SyncLog;
use App\Models\SyncRule;
use App\Models\SyncSelection;
use App\Models\User;
use App\Services\Contacts\AvatarGenerator;
use App\Services\Sync\ContactSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function makeAuthenticatedUser(): User
    {
        return User::factory()->create([
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);
    }

    public function test_it_creates_a_contact_for_a_new_manual_selection(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'given_name' => 'Alice',
            'surname' => 'Martin',
            'job_title' => 'Développeuse',
            'department' => 'IT',
            'content_hash' => DirectoryUser::computeHash(['given_name' => 'Alice', 'surname' => 'Martin']),
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts' => Http::response(['id' => 'outlook-contact-1'], 201),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/me/contacts')
            && $request['givenName'] === 'Alice');

        $selection = SyncSelection::first();
        $this->assertSame('outlook-contact-1', $selection->outlook_contact_id);
        $this->assertNotNull($selection->last_synced_at);
        $this->assertSame(1, SyncLog::where('action', 'created')->count());
    }

    public function test_it_includes_the_company_name_in_the_contact_payload(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'company_name' => 'Company',
            'content_hash' => 'hash',
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts' => Http::response(['id' => 'outlook-contact-1'], 201),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/me/contacts')
            && $request['companyName'] === 'Company');
    }

    public function test_it_copies_the_m365_photo_onto_a_newly_created_contact(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'hash',
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
        ]);

        $photoBytes = 'fake-jpeg-bytes-from-m365';

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1/photo/*' => Http::response('', 204),
            'graph.microsoft.com/v1.0/me/contacts' => Http::response(['id' => 'outlook-contact-1'], 201),
            'graph.microsoft.com/v1.0/users/du-1/photo/*' => Http::response($photoBytes, 200),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/me/contacts/outlook-contact-1/photo/$value')
            && $request->body() === $photoBytes);

        $this->assertNotNull(SyncSelection::first()->photo_synced_at);
        Storage::disk('public')->assertExists('avatars/du-1.jpg');
        $this->assertSame($photoBytes, Storage::disk('public')->get('avatars/du-1.jpg'));
    }

    public function test_it_uses_a_generated_avatar_when_the_person_has_no_m365_photo(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'hash',
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1/photo/*' => Http::response('', 204),
            'graph.microsoft.com/v1.0/me/contacts' => Http::response(['id' => 'outlook-contact-1'], 201),
            'graph.microsoft.com/v1.0/users/du-1/photo/*' => Http::response('', 404),
        ]);

        app(ContactSyncService::class)->run($user);

        $expectedAvatar = AvatarGenerator::generate('Alice Martin');

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/me/contacts/outlook-contact-1/photo/$value')
            && $request->body() === $expectedAvatar);

        $this->assertNotNull(SyncSelection::first()->photo_synced_at);
    }

    public function test_it_does_not_retry_the_photo_once_already_synced(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'same-hash',
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
            'outlook_contact_id' => 'outlook-contact-1',
            'last_hash' => 'same-hash',
            'photo_synced_at' => now()->subDay(),
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1*' => Http::response(['id' => 'outlook-contact-1'], 200),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/photo/'));
    }

    public function test_it_updates_a_contact_when_directory_field_changes(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'job_title' => 'Lead Développeuse',
            'content_hash' => 'new-hash',
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
            'outlook_contact_id' => 'outlook-contact-1',
            'last_hash' => 'old-hash',
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1' => Http::response([], 200),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/me/contacts/outlook-contact-1')
            && $request['jobTitle'] === 'Lead Développeuse');

        $this->assertSame('new-hash', SyncSelection::first()->last_hash);
        $this->assertSame(1, SyncLog::where('action', 'updated')->count());
    }

    public function test_it_only_verifies_existence_when_nothing_changed(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'same-hash',
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
            'outlook_contact_id' => 'outlook-contact-1',
            'last_hash' => 'same-hash',
            // Photo déjà posée sur ce contact : ne doit déclencher aucun appel Graph
            // supplémentaire (get/put photo) pour ce test qui vérifie le chemin "rien n'a changé".
            'photo_synced_at' => now(),
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1*' => Http::response(['id' => 'outlook-contact-1'], 200),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_it_recreates_a_contact_deleted_directly_in_outlook(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'same-hash',
        ]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
            'outlook_contact_id' => 'deleted-in-outlook',
            'last_hash' => 'same-hash',
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/deleted-in-outlook*' => Http::response([], 404),
            'graph.microsoft.com/v1.0/me/contacts' => Http::response(['id' => 'new-contact-id'], 201),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/me/contacts'));

        $selection = SyncSelection::first();
        $this->assertSame('new-contact-id', $selection->outlook_contact_id);
        $this->assertSame(1, SyncLog::where('action', 'created')->count());
    }

    public function test_it_deletes_the_contact_when_person_leaves_the_directory(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'hash',
        ]);

        $selection = SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
            'outlook_contact_id' => 'outlook-contact-1',
            'last_hash' => 'hash',
        ]);

        $directoryUser->delete(); // simule une personne qui a quitté l'annuaire (soft delete)

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1' => Http::response([], 204),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/me/contacts/outlook-contact-1'));

        $selection->refresh();
        $this->assertNull($selection->outlook_contact_id);
        // Une fois le contact réellement supprimé côté Outlook, la fiche ne doit plus apparaître
        // comme "synchronisée" ou "en attente de suppression" côté annuaire.
        $this->assertNull($selection->last_synced_at);
        $this->assertSame(1, SyncLog::where('action', 'deleted')->count());
    }

    public function test_it_deletes_the_contact_of_a_manually_selected_person_disabled_in_m365(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'account_enabled' => true,
            'content_hash' => 'hash',
        ]);

        $selection = SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => true,
            'outlook_contact_id' => 'outlook-contact-1',
            'last_hash' => 'hash',
        ]);

        // Compte désactivé côté M365 mais pas encore retiré de l'annuaire (pas de soft-delete) :
        // la personne existe toujours dans directory_users, juste marquée account_enabled=false.
        $directoryUser->update(['account_enabled' => false]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1' => Http::response([], 204),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/me/contacts/outlook-contact-1'));

        $selection->refresh();
        $this->assertNull($selection->outlook_contact_id);
        $this->assertSame(1, SyncLog::where('action', 'deleted')->count());
    }

    public function test_a_stale_rule_based_selection_is_removed_after_contact_deletion(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'department' => 'Ventes', // ne matche plus la règle IT
            'content_hash' => 'hash',
        ]);

        SyncRule::create(['user_id' => $user->id, 'name' => 'IT', 'department' => 'IT', 'match_type' => 'contains', 'enabled' => true]);

        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'rule',
            'enabled' => true,
            'outlook_contact_id' => 'outlook-contact-1',
            'last_hash' => 'hash',
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1' => Http::response([], 204),
        ]);

        app(ContactSyncService::class)->run($user);

        $this->assertSame(0, SyncSelection::count());
    }

    public function test_a_matching_rule_overrides_a_stale_manual_opt_out(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'department' => 'IT',
            'content_hash' => 'hash',
        ]);

        SyncRule::create(['user_id' => $user->id, 'name' => 'IT', 'department' => 'IT', 'match_type' => 'contains', 'enabled' => true]);

        // Un opt-out manuel fait avant (ou après) la création de cette règle ne doit pas bloquer
        // la personne pour toujours : une règle qui matche reprend la main automatiquement.
        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => false,
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts' => Http::response(['id' => 'new-contact-id'], 201),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/me/contacts'));
        $selection = SyncSelection::first();
        $this->assertSame('rule', $selection->source);
        $this->assertTrue($selection->enabled);
        $this->assertSame('new-contact-id', $selection->outlook_contact_id);
    }

    public function test_a_manual_selection_is_untouched_when_no_rule_matches(): void
    {
        $user = $this->makeAuthenticatedUser();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'department' => 'Ventes',
            'content_hash' => 'hash',
        ]);

        SyncRule::create(['user_id' => $user->id, 'name' => 'IT', 'department' => 'IT', 'match_type' => 'contains', 'enabled' => true]);

        // Personne qu'aucune règle ne concerne : le choix manuel reste seul maître.
        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'source' => 'manual',
            'enabled' => false,
        ]);

        Http::fake();

        app(ContactSyncService::class)->run($user);

        Http::assertNothingSent();
        $this->assertSame('manual', SyncSelection::first()->source);
    }

    public function test_sync_all_enabled_creates_a_contact_without_any_rule_or_manual_selection(): void
    {
        $user = $this->makeAuthenticatedUser();
        $user->update(['sync_all_enabled' => true]);

        DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'hash',
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts' => Http::response(['id' => 'new-contact-id'], 201),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/me/contacts'));
        $this->assertSame('new-contact-id', SyncSelection::first()->outlook_contact_id);
    }

    public function test_disabling_sync_all_removes_contacts_it_had_created(): void
    {
        $user = $this->makeAuthenticatedUser();
        $user->update(['sync_all_enabled' => false]);

        DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'hash',
        ]);

        // Sélection auto-créée précédemment pendant que sync_all_enabled était actif.
        SyncSelection::create([
            'user_id' => $user->id,
            'directory_user_id' => 'du-1',
            'source' => 'rule',
            'enabled' => true,
            'outlook_contact_id' => 'outlook-contact-1',
            'last_hash' => 'hash',
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/me/contacts/outlook-contact-1' => Http::response([], 204),
        ]);

        app(ContactSyncService::class)->run($user);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
        $this->assertSame(0, SyncSelection::count());
    }
}
