<?php

namespace Tests\Feature;

use App\Models\DirectoryUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_person_with_a_cached_avatar_gets_its_url(): void
    {
        $user = User::factory()->create();

        DirectoryUser::create([
            'id' => 'du-with-photo',
            'display_name' => 'Alice Martin',
            'mobile_phone' => '0600000001',
            'account_enabled' => true,
            'content_hash' => 'hash',
        ]);

        DirectoryUser::create([
            'id' => 'du-without-photo',
            'display_name' => 'Bob Durand',
            'mobile_phone' => '0600000002',
            'account_enabled' => true,
            'content_hash' => 'hash',
        ]);

        Storage::disk('public')->put('avatars/du-with-photo.jpg', 'fake-bytes');

        $this->actingAs($user)
            ->get(route('directory.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('directoryUsers.data.0.avatar_url', fn ($url) => str_contains($url, 'avatars/du-with-photo.jpg'))
                ->where('directoryUsers.data.1.avatar_url', null)
            );
    }

    public function test_a_disabled_microsoft_365_account_does_not_appear_in_the_directory(): void
    {
        $user = User::factory()->create();

        DirectoryUser::create([
            'id' => 'du-active',
            'display_name' => 'Alice Martin',
            'mobile_phone' => '0600000001',
            'account_enabled' => true,
            'content_hash' => 'hash-active',
        ]);

        DirectoryUser::create([
            'id' => 'du-disabled',
            'display_name' => 'Bob Durand',
            'mobile_phone' => '0600000002',
            'account_enabled' => false,
            'content_hash' => 'hash-disabled',
        ]);

        $this->actingAs($user)
            ->get(route('directory.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('directoryUsers.total', 1)
                ->where('directoryUsers.data.0.display_name', 'Alice Martin')
            );
    }
}
