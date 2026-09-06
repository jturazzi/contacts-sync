<?php

namespace App\Services\Graph;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GraphClient
{
    public function __construct(protected User $user)
    {
    }

    public function get(string $path): Response
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get($this->url($path)));

        if ($response->status() === 404) {
            return $response;
        }

        return $response->throw();
    }

    public function post(string $path, array $data): Response
    {
        return $this->send(fn (PendingRequest $request) => $request->post($this->url($path), $data))->throw();
    }

    public function patch(string $path, array $data): Response
    {
        $response = $this->send(fn (PendingRequest $request) => $request->patch($this->url($path), $data));

        if ($response->status() === 404) {
            return $response;
        }

        return $response->throw();
    }

    public function delete(string $path): Response
    {
        $response = $this->send(fn (PendingRequest $request) => $request->delete($this->url($path)));

        if ($response->status() === 404) {
            return $response;
        }

        return $response->throw();
    }

    public function getBinaryOrNull(string $path): ?string
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get($this->url($path)));

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->body();
    }

    public function putBinary(string $path, string $content, string $contentType): void
    {
        $this->send(fn (PendingRequest $request) => $request
            ->withBody($content, $contentType)
            ->put($this->url($path)))
            ->throw();
    }

    protected function url(string $path): string
    {
        return rtrim(config('graph.base_url'), '/').'/'.ltrim($path, '/');
    }

    protected function send(callable $callback): Response
    {
        $response = $callback($this->pendingRequest($this->validAccessToken()));

        if ($response->status() !== 401) {
            if ($this->user->microsoft_disconnected_at) {
                $this->user->forceFill(['microsoft_disconnected_at' => null])->save();
            }

            return $response;
        }

        $response = $callback($this->pendingRequest($this->refreshAccessToken()));

        if ($response->status() === 401) {
            $this->user->forceFill(['microsoft_disconnected_at' => now()])->save();
        }

        return $response;
    }

    protected function pendingRequest(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->retry(3, 2000, function ($exception, $request) {
                if (! $exception instanceof \Illuminate\Http\Client\RequestException) {
                    return false;
                }

                return $exception->response->status() === 429;
            }, throw: false);
    }

    protected function validAccessToken(): string
    {
        if ($this->user->access_token && $this->user->token_expires_at?->isFuture()) {
            return $this->user->access_token;
        }

        return $this->refreshAccessToken();
    }

    protected function refreshAccessToken(): string
    {
        if (blank($this->user->refresh_token)) {
            throw new RuntimeException("Aucun refresh_token disponible pour l'utilisateur #{$this->user->id}, reconnexion SSO requise.");
        }

        $tenant = $this->user->tenant_id ?: config('services.microsoft.tenant', 'common');

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->user->refresh_token,
            'scope' => implode(' ', config('graph.scopes')),
        ]);

        if ($response->failed()) {
            $this->user->forceFill(['microsoft_disconnected_at' => now()])->save();

            throw new RuntimeException("Échec du rafraîchissement du token Microsoft pour l'utilisateur #{$this->user->id}: ".$response->body());
        }

        $payload = $response->json();

        $this->user->forceFill([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $this->user->refresh_token,
            'token_expires_at' => now()->addSeconds($payload['expires_in'] ?? 3600),
            'microsoft_disconnected_at' => null,
        ])->save();

        return $payload['access_token'];
    }
}
