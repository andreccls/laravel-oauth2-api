<?php

namespace App\Http\Middleware;

use App\Support\Principal;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\Http\Middleware\ValidateToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * `oauth[:scope,...]` — validates the bearer JWT (signature, expiry, revocation), requires ALL listed scopes
 * and exposes the caller as a Principal. Works for user tokens and client-credentials tokens alike.
 */
final class AuthenticateToken extends ValidateToken
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $token = $this->validateToken($request);
        $this->validate($token, ...$params);

        if (! $token instanceof AccessToken) {
            throw new AuthenticationException;
        }
        $request->attributes->set(Principal::ATTRIBUTE, Principal::fromToken($token));

        return $next($request);
    }

    protected function validate(ScopeAuthorizable $token, string ...$scopes): void
    {
        foreach ($scopes as $scope) {
            if ($token->cant($scope)) {
                throw new MissingScopeException($scope);
            }
        }
    }
}
