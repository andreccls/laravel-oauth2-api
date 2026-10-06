<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\InteractsWithOAuth;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithOAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePassportKeys();
    }
}
