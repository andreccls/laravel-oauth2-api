<?php

namespace Tests\Feature\OAuth;

use App\Models\User;
use App\Support\Scopes;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RevocationTest extends TestCase
{
    public function test_revoked_client_credentials_token_stops_working(): void
    {
        $token = $this->machineToken();

        $this->getJson('/api/me', $this->bearer($token))->assertOk();
        $this->postJson('/api/oauth/revoke', [], $this->bearer($token))->assertNoContent();
        $this->getJson('/api/me', $this->bearer($token))->assertUnauthorized();
    }

    public function test_revoking_also_kills_the_refresh_token(): void
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('spa', ['https://app.example.test/cb'], confidential: false);
        $tokens = $this->authorizationCodeTokens(User::factory()->create(), $client, [Scopes::TASKS_READ]);

        $this->postJson('/api/oauth/revoke', [], $this->bearer($tokens['access_token']))->assertNoContent();

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
            'client_id' => $client->getKey(),
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_revoking_one_token_leaves_the_others_alone(): void
    {
        $client = $this->machineClient();
        $a = $this->clientCredentialsToken($client);
        $b = $this->clientCredentialsToken($client);

        $this->postJson('/api/oauth/revoke', [], $this->bearer($a))->assertNoContent();

        $this->getJson('/api/me', $this->bearer($a))->assertUnauthorized();
        $this->getJson('/api/me', $this->bearer($b))->assertOk();
    }

    public function test_purge_removes_revoked_and_long_expired_tokens_only(): void
    {
        $client = $this->machineClient();
        $revoked = $this->clientCredentialsToken($client);
        $alive = $this->clientCredentialsToken($client);
        $this->postJson('/api/oauth/revoke', [], $this->bearer($revoked))->assertNoContent();
        $this->assertSame(2, Passport::token()->newQuery()->count());

        $this->assertSame(0, Artisan::call('passport:purge'));

        $this->assertSame(1, Passport::token()->newQuery()->count());
        $this->getJson('/api/me', $this->bearer($alive))->assertOk();
    }

    public function test_purge_is_scheduled_daily(): void
    {
        $this->assertSame(0, Artisan::call('schedule:list'));
        $this->assertStringContainsString('passport:purge', Artisan::output());
    }
}
