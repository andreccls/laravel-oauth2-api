<?php

namespace Tests\Feature;

use Tests\TestCase;

class HttpCachingTest extends TestCase
{
    public function test_get_responses_carry_etag_and_private_revalidation_headers(): void
    {
        $auth = $this->bearer($this->machineToken());
        $id = $this->postJson('/api/tasks', ['title' => 'cacheable'], $auth)->json('data.id');

        $response = $this->getJson("/api/tasks/$id", $auth)->assertOk()->assertHeader('ETag');
        $cc = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cc);
        $this->assertStringContainsString('must-revalidate', $cc);
        $this->assertStringContainsString('max-age=0', $cc);
    }

    public function test_if_none_match_returns_304_until_the_resource_changes(): void
    {
        $auth = $this->bearer($this->machineToken());
        $id = $this->postJson('/api/tasks', ['title' => 'v1'], $auth)->json('data.id');

        $etag = $this->getJson("/api/tasks/$id", $auth)->headers->get('ETag');
        $this->getJson("/api/tasks/$id", $auth + ['If-None-Match' => $etag])->assertStatus(304);

        $this->patchJson("/api/tasks/$id", ['title' => 'v2'], $auth)->assertOk();
        $this->getJson("/api/tasks/$id", $auth + ['If-None-Match' => $etag])->assertOk()->assertJsonPath('data.title', 'v2');
    }

    public function test_writes_are_not_cached(): void
    {
        $auth = $this->bearer($this->machineToken());

        $this->postJson('/api/tasks', ['title' => 'x'], $auth)->assertCreated()->assertHeaderMissing('ETag');
    }
}
