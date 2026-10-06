<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    /** The committed docs/openapi.json must match what the code generates (run `make openapi` to refresh). */
    public function test_committed_openapi_document_is_up_to_date(): void
    {
        $path = base_path('docs/openapi.json');
        if (getenv('UPDATE_OPENAPI') && ! file_exists($path)) {
            touch($path);
        }
        $this->assertFileExists($path);

        $tmp = sys_get_temp_dir().'/openapi-'.uniqid().'.json';
        $this->assertSame(0, Artisan::call('scramble:export', ['--path' => $tmp]));

        if (getenv('UPDATE_OPENAPI')) { // `make openapi`
            copy($tmp, $path);
        }
        $this->assertJsonStringEqualsJsonFile($path, (string) file_get_contents($tmp));
        @unlink($tmp);
    }

    public function test_document_describes_every_api_route_and_the_oauth2_scheme(): void
    {
        $doc = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true);

        foreach (['/tasks', '/tasks/{id}', '/me', '/oauth/revoke'] as $path) {
            $this->assertArrayHasKey($path, $doc['paths']);
        }
        $this->assertArrayHasKey('oauth2', $doc['components']['securitySchemes']);
    }
}
