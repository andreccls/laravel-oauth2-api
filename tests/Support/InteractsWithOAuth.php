<?php

namespace Tests\Support;

use App\Models\User;
use App\Support\Scopes;
use Illuminate\Testing\TestResponse as Response;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

trait InteractsWithOAuth
{
    private static bool $keysReady = false;

    protected function ensurePassportKeys(): void
    {
        if (! self::$keysReady && ! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--no-interaction' => true]);
        }
        self::$keysReady = true;
    }

    protected function machineClient(string $name = 'machine'): Client
    {
        return app(ClientRepository::class)->createClientCredentialsGrantClient($name);
    }

    /** @param list<string> $scopes */
    protected function clientCredentialsToken(Client $client, array $scopes = [Scopes::TASKS_READ, Scopes::TASKS_WRITE]): string
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => implode(' ', $scopes),
        ])->assertOk()->json('access_token');
    }

    /** @param list<string> $scopes */
    protected function machineToken(array $scopes = [Scopes::TASKS_READ, Scopes::TASKS_WRITE]): string
    {
        return $this->clientCredentialsToken($this->machineClient(), $scopes);
    }

    /** @return array<string, string> */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    protected const VERIFIER = 'v-0123456789012345678901234567890123456789012345';

    /**
     * Logs in, shows consent and approves, as a public PKCE client would. Returns the authorization code.
     *
     * @param  list<string>  $scopes
     */
    protected function authorizationCode(User $user, Client $client, array $scopes, string $verifier = self::VERIFIER): string
    {
        $redirect = $this->redirectUri($client);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $login = $this->postJson('/login', ['email' => $user->email, 'password' => 'dev-only-password'])->assertNoContent();
        $session = $this->sessionCookieOf($login);

        $consent = $this->withUnencryptedCookie(...$session)->getJson('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))->assertOk();
        $session = $this->sessionCookieOf($consent, $session);

        $approved = $this->withUnencryptedCookie(...$session)->post('/oauth/authorize', [
            'state' => 'xyz',
            'client_id' => $client->getKey(),
            'auth_token' => $consent->json('auth_token'),
        ])->assertRedirectContains($redirect);
        parse_str((string) parse_url((string) $approved->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('xyz', $query['state']);

        $code = $query['code'];
        $this->assertIsString($code);

        return $code;
    }

    protected function redeemCode(Client $client, string $code, string $verifier = self::VERIFIER): Response
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => $this->redirectUri($client),
            'code_verifier' => $verifier,
            'code' => $code,
        ]);
    }

    /**
     * @param  list<string>  $scopes
     * @return array<string, mixed> token response body
     */
    protected function authorizationCodeTokens(User $user, Client $client, array $scopes): array
    {
        return $this->redeemCode($client, $this->authorizationCode($user, $client, $scopes))->assertOk()->json();
    }

    protected function redirectUri(Client $client): string
    {
        $uris = $client->getAttribute('redirect_uris');

        return is_array($uris) ? (string) $uris[0] : '';
    }

    /**
     * @param  array{0: string, 1: string}|null  $fallback
     * @return array{0: string, 1: string} [name, encrypted value] to replay the session cookie in the next request
     */
    private function sessionCookieOf(Response $response, ?array $fallback = null): array
    {
        $cookie = $response->getCookie((string) config('session.cookie'), false);

        return match (true) {
            $cookie !== null => [$cookie->getName(), (string) $cookie->getValue()],
            $fallback !== null => $fallback,
            default => throw new \RuntimeException('No session cookie in response'),
        };
    }
}
