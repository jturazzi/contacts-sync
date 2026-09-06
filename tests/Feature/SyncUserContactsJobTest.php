<?php

namespace Tests\Feature;

use App\Jobs\SyncUserContactsJob;
use App\Models\User;
use App\Services\Directory\DirectoryService;
use App\Services\Sync\ContactSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncUserContactsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_last_sync_completed_at_on_success(): void
    {
        $user = User::factory()->create([
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
            'token_expires_at' => now()->addHour(),
            'last_sync_completed_at' => null,
        ]);

        Http::fake([
            'graph.microsoft.com/v1.0/users*' => Http::response(['value' => []], 200),
        ]);

        (new SyncUserContactsJob($user->id))->handle(
            app(DirectoryService::class),
            app(ContactSyncService::class),
        );

        $this->assertNotNull($user->fresh()->last_sync_completed_at);
    }

    public function test_it_does_not_record_a_completion_time_when_the_user_has_no_valid_token(): void
    {
        $user = User::factory()->create([
            'access_token' => null,
            'refresh_token' => null,
            'last_sync_completed_at' => null,
        ]);

        (new SyncUserContactsJob($user->id))->handle(
            app(DirectoryService::class),
            app(ContactSyncService::class),
        );

        $this->assertNull($user->fresh()->last_sync_completed_at);
    }
}
