<?php

namespace Tests\Unit;

use App\Http\Middleware\AuthenticateToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\TransientToken;
use Tests\TestCase;

class AuthenticateTokenTest extends TestCase
{
    public function test_a_transient_cookie_token_is_not_a_machine_or_user_principal(): void
    {
        $request = Request::create('/api/me');
        $request->setUserResolver(fn () => new class
        {
            public function currentAccessToken(): TransientToken
            {
                return new TransientToken;
            }
        });

        $this->expectException(AuthenticationException::class);
        app(AuthenticateToken::class)->handle($request, fn (Request $r) => new Response);
    }
}
