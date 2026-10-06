<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\Exceptions\OAuthServerException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Maps any exception to an RFC 9457 `application/problem+json` response. */
final class ProblemDetails
{
    /** Returns null when the exception already carries a protocol-defined body (OAuth2 errors, RFC 6749). */
    public static function render(Throwable $e, bool $debug = false): ?JsonResponse
    {
        if ($e instanceof OAuthServerException) {
            return null;
        }

        // Laravel's Handler turns AuthorizationException/ModelNotFoundException into plain HTTP exceptions
        // before our callback runs; unwrap them to keep the precise cause.
        if ($e instanceof HttpExceptionInterface && ($cause = $e->getPrevious()) instanceof AuthorizationException) {
            $e = $cause;
        } elseif ($e instanceof HttpExceptionInterface && $e->getPrevious() instanceof ModelNotFoundException) {
            $e = $e->getPrevious();
        }

        $headers = [];
        $extra = [];

        if ($e instanceof ValidationException) {
            $status = 422;
            $detail = 'The request body or parameters are invalid.';
            $extra['errors'] = $e->errors();
        } elseif ($e instanceof AuthenticationException) {
            $status = 401;
            $detail = 'A valid access token is required.';
            $headers['WWW-Authenticate'] = 'Bearer';
        } elseif ($e instanceof MissingScopeException) {
            $status = 403;
            $detail = 'The access token lacks a required scope.';
            $extra['required_scopes'] = $e->scopes();
        } elseif ($e instanceof AuthorizationException) {
            $status = 403;
            $detail = 'You are not allowed to perform this action.';
        } elseif ($e instanceof ModelNotFoundException) {
            $status = 404;
            $detail = 'The requested resource does not exist.';
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $headers = $e->getHeaders();
            $detail = $status === 404 ? 'The requested resource does not exist.' : ($e->getMessage() ?: null);
            $detail = $status === 429 ? 'Too many requests. Retry after the interval in Retry-After.' : $detail;
        } else {
            $status = 500;
            $detail = $debug ? $e->getMessage() : null;
        }

        $body = array_filter([
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $detail,
        ] + $extra, fn ($v) => $v !== null);

        return new JsonResponse($body, $status, $headers + ['Content-Type' => 'application/problem+json']);
    }
}
