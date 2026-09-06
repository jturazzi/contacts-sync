<?php

namespace Tests\Feature;

use App\Models\DirectoryUser;
use App\Models\User;
use App\Services\Directory\DirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DirectoryServiceTest extends TestCase
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

    public function test_it_only_keeps_people_with_a_phone_number(): void
    {
        Http::fake([
            'graph.microsoft.com/v1.0/users*' => Http::response([
                'value' => [
                    ['id' => 'has-mobile', 'displayName' => 'A', 'department' => 'IT', 'mobilePhone' => '0600000001', 'businessPhones' => []],
                    ['id' => 'has-business', 'displayName' => 'B', 'department' => 'IT', 'mobilePhone' => null, 'businessPhones' => ['0100000002']],
                    ['id' => 'no-phone', 'displayName' => 'C', 'department' => 'IT', 'mobilePhone' => null, 'businessPhones' => []],
                ],
            ], 200),
        ]);

        app(DirectoryService::class)->refresh($this->makeAuthenticatedUser());

        $this->assertTrue(DirectoryUser::query()->whereKey('has-mobile')->exists());
        $this->assertTrue(DirectoryUser::query()->whereKey('has-business')->exists());
        $this->assertFalse(DirectoryUser::query()->whereKey('no-phone')->exists());
    }

    public function test_refresh_deletes_cached_avatars_of_people_no_longer_active(): void
    {
        $user = $this->makeAuthenticatedUser();

        DirectoryUser::create([
            'id' => 'du-stays',
            'display_name' => 'Stays',
            'mobile_phone' => '0600000001',
            'account_enabled' => true,
            'content_hash' => 'hash',
        ]);

        DirectoryUser::create([
            'id' => 'du-leaves',
            'display_name' => 'Leaves',
            'mobile_phone' => '0600000002',
            'account_enabled' => true,
            'content_hash' => 'hash',
        ]);

        Storage::disk('public')->put('avatars/du-stays.jpg', 'bytes');
        Storage::disk('public')->put('avatars/du-leaves.jpg', 'bytes');

        // du-leaves n'apparaît plus dans l'import : elle a quitté l'annuaire.
        Http::fake([
            'graph.microsoft.com/v1.0/users*' => Http::response([
                'value' => [
                    ['id' => 'du-stays', 'displayName' => 'Stays', 'mobilePhone' => '0600000001', 'businessPhones' => []],
                ],
            ], 200),
        ]);

        app(DirectoryService::class)->refresh($user);

        Storage::disk('public')->assertExists('avatars/du-stays.jpg');
        Storage::disk('public')->assertMissing('avatars/du-leaves.jpg');
    }

    public function test_department_field_is_persisted(): void
    {
        Http::fake([
            'graph.microsoft.com/v1.0/users*' => Http::response([
                'value' => [
                    ['id' => 'du-1', 'displayName' => 'Alice', 'department' => 'Support IT', 'jobTitle' => 'Technicienne', 'mobilePhone' => '0600000001', 'businessPhones' => []],
                ],
            ], 200),
        ]);

        app(DirectoryService::class)->refresh($this->makeAuthenticatedUser());

        $this->assertSame('Support IT', DirectoryUser::find('du-1')->department);
    }

    public function test_a_person_absent_from_the_latest_import_is_removed(): void
    {
        $user = $this->makeAuthenticatedUser();
        DirectoryUser::create(['id' => 'gone', 'display_name' => 'Old', 'mobile_phone' => '0600000000', 'content_hash' => 'x']);

        Http::fake([
            'graph.microsoft.com/v1.0/users*' => Http::response(['value' => []], 200),
        ]);

        app(DirectoryService::class)->refresh($user);

        $this->assertTrue(DirectoryUser::withTrashed()->find('gone')->trashed());
    }

    public function test_pagination_follows_next_link(): void
    {
        Http::fake([
            // La règle la plus spécifique doit être déclarée en premier : Http::fake() retient
            // le premier motif qui matche, et le second appel matcherait sinon le motif générique.
            'graph.microsoft.com/v1.0/users?$skiptoken=abc' => Http::response([
                'value' => [['id' => 'page-2', 'displayName' => 'B', 'mobilePhone' => '0600000002', 'businessPhones' => []]],
            ], 200),
            'graph.microsoft.com/v1.0/users?*' => Http::response([
                'value' => [['id' => 'page-1', 'displayName' => 'A', 'mobilePhone' => '0600000001', 'businessPhones' => []]],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users?$skiptoken=abc',
            ], 200),
        ]);

        app(DirectoryService::class)->refresh($this->makeAuthenticatedUser());

        $this->assertSame(2, DirectoryUser::count());
    }
}
