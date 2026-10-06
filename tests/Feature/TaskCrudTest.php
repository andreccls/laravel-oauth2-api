<?php

namespace Tests\Feature;

use App\Models\Task;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    /** @return array<string, string> */
    private function auth(?string $token = null): array
    {
        return $this->bearer($token ?? $this->machineToken());
    }

    public function test_create_returns_201_with_location_and_items(): void
    {
        $response = $this->postJson('/api/tasks', [
            'title' => 'Ship it',
            'items' => [['title' => 'write'], ['title' => 'test', 'done' => true]],
        ], $this->auth())->assertCreated();

        $id = $response->json('data.id');
        $response->assertHeader('Location', url("/api/tasks/$id"))
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.items.1.done', true)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_validation_errors_are_problem_details_422(): void
    {
        $this->postJson('/api/tasks', ['title' => '', 'status' => 'nope', 'items' => [['done' => 'maybe']]], $this->auth())
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonValidationErrors(['title', 'status', 'items.0.title', 'items.0.done']);
    }

    public function test_list_is_paginated_filtered_and_newest_first(): void
    {
        $client = $this->machineClient();
        $token = $this->clientCredentialsToken($client);
        Task::factory()->count(5)->ownedBy('client:'.$client->getKey())->create();
        Task::factory()->ownedBy('client:'.$client->getKey())->create(['status' => 'done', 'title' => 'finished']);

        $page = $this->getJson('/api/tasks?per_page=4', $this->auth($token))->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.title', 'finished');
        $this->assertNotNull($page->json('links.next'));

        $this->getJson('/api/tasks?status=done', $this->auth($token))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?per_page=1000', $this->auth($token))->assertStatus(422);
    }

    public function test_show_update_patch_and_delete(): void
    {
        $auth = $this->auth();
        $id = $this->postJson('/api/tasks', ['title' => 'a', 'items' => [['title' => 'x']]], $auth)->json('data.id');

        $this->getJson("/api/tasks/$id", $auth)->assertOk()->assertJsonPath('data.title', 'a');

        // PATCH without items keeps them; only sent fields change.
        $this->patchJson("/api/tasks/$id", ['status' => 'done'], $auth)->assertOk()
            ->assertJsonPath('data.status', 'done')->assertJsonPath('data.title', 'a')->assertJsonCount(1, 'data.items');

        // PUT with items replaces them.
        $this->putJson("/api/tasks/$id", ['title' => 'b', 'items' => [['title' => 'y'], ['title' => 'z']]], $auth)->assertOk()
            ->assertJsonPath('data.title', 'b')->assertJsonCount(2, 'data.items');

        $this->deleteJson("/api/tasks/$id", [], $auth)->assertNoContent();
        $this->getJson("/api/tasks/$id", $auth)->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
        $this->assertDatabaseCount('task_items', 0);
    }

    public function test_other_principals_tasks_are_invisible_not_forbidden(): void
    {
        $mine = $this->auth();
        $theirs = $this->auth();
        $id = $this->postJson('/api/tasks', ['title' => 'secret'], $theirs)->json('data.id');

        $this->getJson("/api/tasks/$id", $mine)->assertNotFound();
        $this->putJson("/api/tasks/$id", ['title' => 'hacked'], $mine)->assertNotFound();
        $this->deleteJson("/api/tasks/$id", [], $mine)->assertNotFound();
        $this->getJson('/api/tasks', $mine)->assertJsonCount(0, 'data');
        $this->assertSame('secret', Task::query()->where('id', $id)->value('title'));
    }

    public function test_unknown_route_is_a_problem_details_404(): void
    {
        $this->getJson('/api/nope', $this->auth())->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
    }
}
