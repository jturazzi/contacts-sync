<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Graph\GraphClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GraphClientTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        return User::factory()->create([
            'access_token' => 'stale-but-not-yet-expired',
            'refresh_token' => 'valid-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);
    }

    public function test_a_401_on_a_call_forces_a_token_refresh_and_retries(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'valid-refresh-token',
                'expires_in' => 3600,
            ], 200),
            'graph.microsoft.com/v1.0/me*' => Http::sequence()
                ->push(['error' => 'InvalidAuthenticationToken'], 401)
                ->push(['id' => 'me-id'], 200),
        ]);

        $user = $this->makeUser();

        $response = (new GraphClient($user))->get('/me');

        $this->assertSame(200, $response->status());
        $this->assertNull($user->fresh()->microsoft_disconnected_at);
    }

    public function test_a_401_that_persists_after_refresh_marks_the_user_disconnected(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'valid-refresh-token',
                'expires_in' => 3600,
            ], 200),
            'graph.microsoft.com/v1.0/me*' => Http::response(['error' => 'InvalidAuthenticationToken'], 401),
        ]);

        $user = $this->makeUser();

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        try {
            (new GraphClient($user))->get('/me');
        } finally {
            $this->assertNotNull($user->fresh()->microsoft_disconnected_at);
        }
    }

    public function test_a_successful_call_clears_a_previous_disconnected_flag(): void
    {
        Http::fake([
            'graph.microsoft.com/v1.0/me*' => Http::response(['id' => 'me-id'], 200),
        ]);

        $user = $this->makeUser();
        $user->forceFill(['microsoft_disconnected_at' => now()->subMinute()])->save();

        (new GraphClient($user))->get('/me');

        $this->assertNull($user->fresh()->microsoft_disconnected_at);
    }
}
