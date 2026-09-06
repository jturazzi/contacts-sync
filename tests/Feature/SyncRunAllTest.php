<?php

namespace Tests\Feature;

use App\Jobs\SyncUserContactsJob;
use App\Models\DirectoryUser;
use App\Models\SyncRule;
use App\Models\SyncSelection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncRunAllTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUserWithToken(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'refresh_token' => 'valid-refresh-token',
        ], $attributes));
    }

    public function test_it_dispatches_for_a_user_with_an_enabled_manual_selection(): void
    {
        Queue::fake();

        $user = $this->makeUserWithToken();
        $directoryUser = DirectoryUser::create(['id' => 'du-1', 'display_name' => 'Alice', 'content_hash' => 'h']);
        SyncSelection::create(['user_id' => $user->id, 'directory_user_id' => $directoryUser->id, 'source' => 'manual', 'enabled' => true]);

        $this->artisan('sync:run-all');

        Queue::assertPushed(SyncUserContactsJob::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_it_dispatches_for_a_user_with_an_active_rule(): void
    {
        Queue::fake();

        $user = $this->makeUserWithToken();
        SyncRule::create(['user_id' => $user->id, 'name' => 'IT', 'department' => 'IT', 'match_type' => 'contains', 'enabled' => true]);

        $this->artisan('sync:run-all');

        Queue::assertPushed(SyncUserContactsJob::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_it_dispatches_for_a_user_with_sync_all_enabled_and_no_selection_yet(): void
    {
        Queue::fake();

        // Cas précis corrigé : "tout synchroniser" vient d'être activé, aucune sync_selection
        // n'existe encore (ex: le tout premier job a échoué avant d'en créer) - le cycle
        // planifié doit quand même reprendre cet utilisateur.
        $user = $this->makeUserWithToken(['sync_all_enabled' => true]);

        $this->artisan('sync:run-all');

        Queue::assertPushed(SyncUserContactsJob::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_it_skips_a_user_with_nothing_active(): void
    {
        Queue::fake();

        $this->makeUserWithToken();

        $this->artisan('sync:run-all');

        Queue::assertNotPushed(SyncUserContactsJob::class);
    }

    public function test_it_skips_a_user_without_a_refresh_token(): void
    {
        Queue::fake();

        $user = User::factory()->create(['refresh_token' => null, 'sync_all_enabled' => true]);

        $this->artisan('sync:run-all');

        Queue::assertNotPushed(SyncUserContactsJob::class, fn ($job) => $job->userId === $user->id);
    }
}
