<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver('microsoft');

        return $driver
            ->scopes(config('graph.scopes'))
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        /** @var \SocialiteProviders\Microsoft\Provider $driver */
        $driver = Socialite::driver('microsoft');
        $msUser = $driver->user();

        $user = User::query()->updateOrCreate(
            ['ms_id' => $msUser->getId()],
            [
                'name' => $msUser->getName() ?: $msUser->getNickname(),
                'email' => $msUser->user['mail'] ?? $msUser->getEmail(),
                'avatar' => $msUser->getAvatar(),
                'tenant_id' => $driver->getClaims()?->tid,
                'access_token' => $msUser->token,
                'refresh_token' => $msUser->refreshToken,
                // @phpstan-ignore nullCoalesce.property (Socialite type-hints expiresIn as non-nullable int, but it is genuinely unset when the provider omits it)
                'token_expires_at' => now()->addSeconds($msUser->expiresIn ?? 3600),
                'microsoft_disconnected_at' => null,
            ],
        );

        Auth::login($user, remember: true);

        return redirect()->route('directory.index');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
