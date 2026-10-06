<?php

namespace Tests\Feature\OAuth;

use App\Models\User;
use App\Support\Scopes;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AuthorizationCodePkceTest extends TestCase
{
    private const REDIRECT = 'https://app.example.test/callback';

    private function publicClient(): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient('spa', [self::REDIRECT], confidential: false);
    }

    public function test_full_flow_with_pkce_then_refresh_rotation(): void
    {
        $user = User::factory()->create();
        $client = $this->publicClient();

        $tokens = $this->authorizationCodeTokens($user, $client, [Scopes::TASKS_READ]);

        $this->getJson('/api/me', $this->bearer($tokens['access_token']))
            ->assertOk()
            ->assertJsonPath('subject_type', 'user')
            ->assertJsonPath('subject_id', (string) $user->id)
            ->assertJsonPath('scopes', [Scopes::TASKS_READ]);

        $refresh = fn (string $token) => $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token,
            'client_id' => $client->getKey(),
        ]);

        $renewed = $refresh($tokens['refresh_token'])->assertOk()->json();
        $this->assertNotSame($tokens['access_token'], $renewed['access_token']);
        $this->getJson('/api/me', $this->bearer($renewed['access_token']))->assertOk();

        // Refresh tokens are single-use: replaying the old one fails.
        $refresh($tokens['refresh_token'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_wrong_code_verifier_is_rejected(): void
    {
        $user = User::factory()->create();
        $client = $this->publicClient();

        $code = $this->authorizationCode($user, $client, [Scopes::TASKS_READ]);

        $this->redeemCode($client, $code, 'a-different-verifier-0123456789012345678901234567')
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_public_client_without_pkce_challenge_is_rejected(): void
    {
        $user = User::factory()->create();
        $client = $this->publicClient();
        $this->actingAs($user);

        $this->getJson('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'scope' => Scopes::TASKS_READ,
            'state' => 'xyz',
        ]))->assertStatus(400)->assertJsonPath('error', 'invalid_request');
    }

    public function test_authorize_requires_a_logged_in_user(): void
    {
        $client = $this->publicClient();

        $this->getJson('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'state' => 'xyz',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_login_rejects_bad_credentials_with_problem_details(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonValidationErrors('email');
    }

    public function test_client_credentials_client_cannot_use_the_authorization_code_grant(): void
    {
        $client = $this->machineClient();

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'redirect_uri' => self::REDIRECT,
            'code' => 'x',
        ])->assertStatus(400);
    }
}
