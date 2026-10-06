<?php

namespace Tests\Feature;

use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_exceeding_the_limit_returns_429_problem_details_with_retry_after(): void
    {
        config(['api.rate_limit' => 3]);
        $token = $this->machineToken();

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/me', $this->bearer($token))->assertOk();
        }

        $this->getJson('/api/me', $this->bearer($token))
            ->assertStatus(429)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Retry-After')
            ->assertJson(['status' => 429]);
    }

    public function test_limit_is_per_client_not_global_and_not_reset_by_new_tokens(): void
    {
        config(['api.rate_limit' => 2]);
        $client = $this->machineClient();
        $first = $this->clientCredentialsToken($client);
        $second = $this->clientCredentialsToken($client);
        $other = $this->machineToken();

        $this->getJson('/api/me', $this->bearer($first))->assertOk();
        $this->getJson('/api/me', $this->bearer($second))->assertOk();
        // Same client, new token: still shares the budget.
        $this->getJson('/api/me', $this->bearer($first))->assertStatus(429);
        // A different client has its own budget.
        $this->getJson('/api/me', $this->bearer($other))->assertOk();
    }

    public function test_login_is_throttled_by_email_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertStatus(422);
        }
        $this->postJson('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertStatus(429);
    }
}
