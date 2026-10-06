<?php

namespace Tests\Unit;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use PHPUnit\Framework\TestCase;

class UpdateTaskRequestTest extends TestCase
{
    public function test_update_makes_top_level_fields_optional_but_keeps_nested_rules(): void
    {
        $store = (new StoreTaskRequest)->rules();
        $update = (new UpdateTaskRequest)->rules();

        $this->assertContains('required', $store['title']);
        $this->assertSame('sometimes', $update['title'][0]);
        $this->assertSame($store['items.*.title'], $update['items.*.title']);
        $this->assertSame($store['items'], $update['items']);
    }

    public function test_both_requests_are_authorized_because_scopes_are_checked_by_the_route(): void
    {
        $this->assertTrue((new StoreTaskRequest)->authorize());
        $this->assertTrue((new UpdateTaskRequest)->authorize());
    }
}
