<?php

namespace Tests\Feature;

use App\Models\DirectoryUser;
use App\Models\SyncRule;
use App\Models\User;
use App\Services\Sync\RuleMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncRuleMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contains_match_is_case_insensitive(): void
    {
        $rule = new SyncRule(['department' => 'it', 'match_type' => 'contains']);
        $directoryUser = DirectoryUser::create(['id' => 'du-1', 'department' => 'Support IT']);

        $this->assertTrue($rule->matches($directoryUser));
    }

    public function test_exact_match_rejects_partial_value(): void
    {
        $rule = new SyncRule(['department' => 'IT', 'match_type' => 'exact']);
        $directoryUser = DirectoryUser::create(['id' => 'du-1', 'department' => 'Support IT']);

        $this->assertFalse($rule->matches($directoryUser));
    }

    public function test_rule_with_no_criteria_matches_nobody(): void
    {
        $rule = new SyncRule(['match_type' => 'contains']);
        $directoryUser = DirectoryUser::create(['id' => 'du-1', 'department' => 'IT']);

        $this->assertFalse($rule->matches($directoryUser));
    }

    public function test_rule_requires_all_specified_fields_to_match(): void
    {
        $rule = new SyncRule(['department' => 'IT', 'job_title' => 'Manager', 'match_type' => 'contains']);
        $directoryUser = DirectoryUser::create(['id' => 'du-1', 'department' => 'IT', 'job_title' => 'Développeur']);

        $this->assertFalse($rule->matches($directoryUser));
    }

    public function test_rule_matcher_only_returns_enabled_accounts(): void
    {
        $user = User::factory()->create();
        SyncRule::create(['user_id' => $user->id, 'department' => 'IT', 'match_type' => 'contains', 'name' => 'IT', 'enabled' => true]);

        DirectoryUser::create(['id' => 'active', 'department' => 'IT', 'account_enabled' => true]);
        DirectoryUser::create(['id' => 'disabled', 'department' => 'IT', 'account_enabled' => false]);

        $matches = app(RuleMatcher::class)->matchedDirectoryUsers($user);

        $this->assertTrue($matches->has('active'));
        $this->assertFalse($matches->has('disabled'));
    }

    public function test_disabled_rule_is_ignored(): void
    {
        $user = User::factory()->create();
        SyncRule::create(['user_id' => $user->id, 'department' => 'IT', 'match_type' => 'contains', 'name' => 'IT', 'enabled' => false]);
        DirectoryUser::create(['id' => 'du-1', 'department' => 'IT', 'account_enabled' => true]);

        $matches = app(RuleMatcher::class)->matchedDirectoryUsers($user);

        $this->assertTrue($matches->isEmpty());
    }

    public function test_sync_all_enabled_matches_every_active_directory_user_regardless_of_rules(): void
    {
        $user = User::factory()->create(['sync_all_enabled' => true]);

        DirectoryUser::create(['id' => 'du-1', 'department' => 'IT', 'account_enabled' => true]);
        DirectoryUser::create(['id' => 'du-2', 'department' => 'Ventes', 'account_enabled' => true]);
        DirectoryUser::create(['id' => 'disabled', 'department' => 'Ventes', 'account_enabled' => false]);

        $matches = app(RuleMatcher::class)->matchedDirectoryUsers($user);

        $this->assertTrue($matches->has('du-1'));
        $this->assertTrue($matches->has('du-2'));
        $this->assertFalse($matches->has('disabled'));
    }
}
