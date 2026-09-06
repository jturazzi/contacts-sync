<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use SocialiteProviders\Microsoft\Provider;
use Tests\TestCase;

class MicrosoftAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeSocialiteUser(): SocialiteUser
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'ms-object-id-123';
        $socialiteUser->name = 'Alice Martin';
        $socialiteUser->nickname = null;
        $socialiteUser->email = null;
        $socialiteUser->avatar = 'https://example.com/avatar.jpg';
        $socialiteUser->token = 'access-token-abc';
        $socialiteUser->refreshToken = 'refresh-token-abc';
        $socialiteUser->expiresIn = 3600;
        $socialiteUser->user = ['mail' => 'alice.martin@company.com'];

        return $socialiteUser;
    }

    protected function mockSocialite(SocialiteUser $socialiteUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        $provider->shouldReceive('getClaims')->andReturn((object) ['tid' => 'tenant-123']);

        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);
    }

    public function test_callback_creates_a_new_user_and_logs_them_in(): void
    {
        $this->mockSocialite($this->fakeSocialiteUser());

        $response = $this->get(route('auth.callback'));

        $response->assertRedirect(route('directory.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'ms_id' => 'ms-object-id-123',
            'email' => 'alice.martin@company.com',
            'tenant_id' => 'tenant-123',
        ]);
    }

    public function test_callback_updates_the_existing_user_instead_of_duplicating(): void
    {
        $existing = User::factory()->create([
            'ms_id' => 'ms-object-id-123',
            'name' => 'Old Name',
            'refresh_token' => 'stale-refresh-token',
            'microsoft_disconnected_at' => now(),
        ]);

        $this->mockSocialite($this->fakeSocialiteUser());

        $this->get(route('auth.callback'));

        $this->assertSame(1, User::count());
        $existing->refresh();
        $this->assertSame('Alice Martin', $existing->name);
        $this->assertSame('refresh-token-abc', $existing->refresh_token);
        // Une reconnexion réussie efface tout marquage "déconnecté" précédent.
        $this->assertNull($existing->microsoft_disconnected_at);
    }

    public function test_logout_clears_the_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
