<?php

namespace Tests\Unit;

use App\Support\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\Exceptions\OAuthServerException;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;
use Throwable;

class ProblemDetailsTest extends TestCase
{
    private function problem(Throwable $e, bool $debug = false): JsonResponse
    {
        $response = ProblemDetails::render($e, $debug);
        $this->assertNotNull($response);

        return $response;
    }

    /** @return iterable<string, array{Throwable, int}> */
    public static function mappings(): iterable
    {
        yield 'authentication' => [new AuthenticationException, 401];
        yield 'missing scope' => [new MissingScopeException('tasks:write'), 403];
        yield 'authorization' => [new AuthorizationException, 403];
        yield 'model not found' => [new ModelNotFoundException, 404];
        yield 'route not found' => [new NotFoundHttpException, 404];
        yield 'too many' => [new TooManyRequestsHttpException(30), 429];
        yield 'generic http' => [new HttpException(409, 'conflict'), 409];
        yield 'unexpected' => [new RuntimeException('boom'), 500];
    }

    #[DataProvider('mappings')]
    public function test_status_and_content_type(Throwable $e, int $status): void
    {
        $response = $this->problem($e);

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertSame($status, $response->getData(true)['status']);
    }

    public function test_validation_errors_are_included(): void
    {
        $e = ValidationException::withMessages(['title' => ['required']]);

        $this->assertSame(['title' => ['required']], $this->problem($e)->getData(true)['errors']);
    }

    public function test_missing_scope_lists_required_scopes_and_401_sets_www_authenticate(): void
    {
        $this->assertSame(['a:b'], $this->problem(new MissingScopeException('a:b'))->getData(true)['required_scopes']);
        $this->assertSame('Bearer', $this->problem(new AuthenticationException)->headers->get('WWW-Authenticate'));
    }

    public function test_causes_wrapped_by_laravel_are_unwrapped(): void
    {
        // Laravel's Handler wraps AuthorizationException into a 403 HttpException before our callback runs.
        $wrapped = new HttpException(403, 'x', new MissingScopeException('tasks:read'));

        $this->assertSame(['tasks:read'], $this->problem($wrapped)->getData(true)['required_scopes']);
    }

    public function test_retry_after_header_is_preserved_on_429(): void
    {
        $this->assertSame('30', $this->problem(new TooManyRequestsHttpException(30))->headers->get('Retry-After'));
    }

    public function test_internal_errors_never_leak_unless_debug(): void
    {
        $e = new RuntimeException('secret db password');

        $this->assertArrayNotHasKey('detail', $this->problem($e)->getData(true));
        $this->assertSame('secret db password', $this->problem($e, debug: true)->getData(true)['detail']);
    }

    public function test_oauth_protocol_errors_are_left_to_passport(): void
    {
        $this->assertNull(ProblemDetails::render(new OAuthServerException(LeagueException::invalidScope('x'))));
    }
}
