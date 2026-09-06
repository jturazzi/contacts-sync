<?php

namespace Tests\Feature;

use App\Models\DirectoryUser;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneSyncLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_logs_older_than_the_retention_period(): void
    {
        config(['graph.sync_log_retention_days' => 90]);

        $user = User::factory()->create();
        $directoryUser = DirectoryUser::create([
            'id' => 'du-1',
            'display_name' => 'Alice Martin',
            'content_hash' => 'hash',
        ]);

        $old = SyncLog::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'action' => 'created',
            'message' => 'old',
        ]);
        $old->forceFill(['created_at' => now()->subDays(120)])->save();

        $recent = SyncLog::create([
            'user_id' => $user->id,
            'directory_user_id' => $directoryUser->id,
            'action' => 'created',
            'message' => 'recent',
        ]);
        $recent->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('sync:prune-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('sync_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('sync_logs', ['id' => $recent->id]);
    }
}
