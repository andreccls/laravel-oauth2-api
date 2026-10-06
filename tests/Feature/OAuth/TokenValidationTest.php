<?php

namespace Tests\Feature\OAuth;

use App\Support\Scopes;
use DateInterval;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TokenValidationTest extends TestCase
{
    public function test_missing_token_is_401_problem_details_with_www_authenticate(): void
    {
        $this->getJson('/api/tasks')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('WWW-Authenticate', 'Bearer')
            ->assertJson(['status' => 401, 'title' => 'Unauthorized']);
    }

    public function test_garbage_token_is_401(): void
    {
        $this->getJson('/api/tasks', $this->bearer('not-a-jwt'))->assertUnauthorized();
    }

    public function test_expired_token_is_401(): void
    {
        $past = new DateInterval('PT1M');
        $past->invert = 1;
        Passport::tokensExpireIn($past);
        $token = $this->machineToken();

        $this->getJson('/api/me', $this->bearer($token))->assertUnauthorized();
    }

    public function test_insufficient_scope_is_403_and_names_the_missing_scope(): void
    {
        $token = $this->machineToken([Scopes::TASKS_READ]);

        $this->postJson('/api/tasks', ['title' => 'x'], $this->bearer($token))
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['status' => 403, 'required_scopes' => [Scopes::TASKS_WRITE]]);
    }

    public function test_write_scope_does_not_imply_read(): void
    {
        $token = $this->machineToken([Scopes::TASKS_WRITE]);

        $this->getJson('/api/tasks', $this->bearer($token))->assertForbidden();
    }

    public function test_token_without_scopes_can_only_call_me(): void
    {
        $token = $this->machineToken([]);

        $this->getJson('/api/me', $this->bearer($token))->assertOk()->assertJsonPath('scopes', []);
        $this->getJson('/api/tasks', $this->bearer($token))->assertForbidden();
    }
}
