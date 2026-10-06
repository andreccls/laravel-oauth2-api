<?php

namespace Tests\Feature\OAuth;

use App\Support\Scopes;
use Tests\TestCase;

class ClientCredentialsTest extends TestCase
{
    public function test_issues_a_token_and_me_describes_a_machine_principal(): void
    {
        $client = $this->machineClient();
        $token = $this->clientCredentialsToken($client, [Scopes::TASKS_READ]);

        $this->getJson('/api/me', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('subject_type', 'client')
            ->assertJsonPath('subject_id', $client->getKey())
            ->assertJsonPath('client_id', $client->getKey())
            ->assertJsonPath('scopes', [Scopes::TASKS_READ])
            ->assertJsonStructure(['expires_at']);
    }

    public function test_token_response_has_a_short_expiry_and_no_refresh_token(): void
    {
        $client = $this->machineClient();

        $body = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => Scopes::TASKS_READ,
        ])->assertOk()->json();

        $this->assertSame('Bearer', $body['token_type']);
        $this->assertSame(3600, $body['expires_in']);
        $this->assertArrayNotHasKey('refresh_token', $body);
    }

    public function test_wrong_secret_is_rejected_with_an_oauth_error(): void
    {
        $client = $this->machineClient();

        $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => 'wrong',
        ])->assertUnauthorized()->assertJsonPath('error', 'invalid_client');
    }

    public function test_unknown_scope_is_rejected(): void
    {
        $client = $this->machineClient();

        $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => 'admin:everything',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
    }

    public function test_password_grant_is_not_available(): void
    {
        $client = $this->machineClient();

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'username' => 'a@b.c',
            'password' => 'x',
        ])->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');
    }
}
