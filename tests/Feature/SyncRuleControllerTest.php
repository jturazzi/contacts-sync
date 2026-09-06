<?php

namespace Tests\Feature;

use App\Jobs\SyncUserContactsJob;
use App\Models\DirectoryUser;
use App\Models\SyncRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SyncRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_rule_and_dispatches_a_sync(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('rules.store'), [
                'name' => 'Equipe IT',
                'department' => 'IT',
                'match_type' => 'contains',
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sync_rules', ['user_id' => $user->id, 'name' => 'Equipe IT']);
        Queue::assertPushed(SyncUserContactsJob::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_the_flash_message_follows_the_users_locale_preference(): void
    {
        Queue::fake();

        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->post(route('rules.store'), [
            'name' => 'IT Team',
            'department' => 'IT',
            'match_type' => 'contains',
            'enabled' => true,
        ]);

        $response->assertSessionHas('success', 'Rule created.');
    }

    public function test_store_requires_a_valid_match_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('rules.store'), [
                'name' => 'Equipe IT',
                'department' => 'IT',
                'match_type' => 'not-a-real-option',
            ])
            ->assertSessionHasErrors('match_type');
    }

    public function test_a_user_cannot_update_another_users_rule(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $rule = SyncRule::create(['user_id' => $owner->id, 'name' => 'IT', 'department' => 'IT', 'match_type' => 'contains', 'enabled' => true]);

        $this->actingAs($intruder)
            ->patch(route('rules.update', $rule->id), [
                'name' => 'Hijacked',
                'match_type' => 'contains',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('sync_rules', ['id' => $rule->id, 'name' => 'IT']);
    }

    public function test_a_user_cannot_delete_another_users_rule(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $rule = SyncRule::create(['user_id' => $owner->id, 'name' => 'IT', 'department' => 'IT', 'match_type' => 'contains', 'enabled' => true]);

        $this->actingAs($intruder)
            ->delete(route('rules.destroy', $rule->id))
            ->assertForbidden();

        $this->assertDatabaseHas('sync_rules', ['id' => $rule->id]);
    }

    public function test_index_reports_matched_names_for_each_rule(): void
    {
        $user = User::factory()->create();
        DirectoryUser::create(['id' => 'du-1', 'display_name' => 'Alice Martin', 'department' => 'IT', 'account_enabled' => true, 'content_hash' => 'h']);
        SyncRule::create(['user_id' => $user->id, 'name' => 'IT', 'department' => 'IT', 'match_type' => 'contains', 'enabled' => true]);

        $this->actingAs($user)
            ->get(route('rules.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rules.0.matches_count', 1)
                ->where('rules.0.matched_names.0', 'Alice Martin')
            );
    }

    public function test_preview_returns_the_count_and_names_without_persisting_anything(): void
    {
        $user = User::factory()->create();
        DirectoryUser::create(['id' => 'du-1', 'display_name' => 'Alice Martin', 'department' => 'IT', 'account_enabled' => true, 'content_hash' => 'h']);

        $this->actingAs($user)
            ->postJson(route('rules.preview'), ['department' => 'IT', 'match_type' => 'contains'])
            ->assertJson(['count' => 1, 'names' => ['Alice Martin']]);

        $this->assertSame(0, SyncRule::count());
    }
}
