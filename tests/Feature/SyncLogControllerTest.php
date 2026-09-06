<?php

namespace Tests\Feature;

use App\Models\DirectoryUser;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SyncLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_shows_the_authenticated_users_own_logs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $directoryUser = DirectoryUser::create(['id' => 'du-1', 'display_name' => 'Alice Martin', 'content_hash' => 'h']);

        SyncLog::create(['user_id' => $user->id, 'directory_user_id' => $directoryUser->id, 'action' => 'created', 'message' => 'mine']);
        SyncLog::create(['user_id' => $otherUser->id, 'directory_user_id' => $directoryUser->id, 'action' => 'created', 'message' => 'not mine']);

        $this->actingAs($user)
            ->get(route('logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.total', 1)
                ->where('logs.data.0.message', 'mine')
            );
    }

    public function test_logs_are_ordered_most_recent_first(): void
    {
        $user = User::factory()->create();
        $directoryUser = DirectoryUser::create(['id' => 'du-1', 'display_name' => 'Alice Martin', 'content_hash' => 'h']);

        $older = SyncLog::create(['user_id' => $user->id, 'directory_user_id' => $directoryUser->id, 'action' => 'created', 'message' => 'older']);
        $older->forceFill(['created_at' => now()->subDay()])->save();

        SyncLog::create(['user_id' => $user->id, 'directory_user_id' => $directoryUser->id, 'action' => 'updated', 'message' => 'newer']);

        $this->actingAs($user)
            ->get(route('logs.index'))
            ->assertInertia(fn (Assert $page) => $page->where('logs.data.0.message', 'newer'));
    }
}
