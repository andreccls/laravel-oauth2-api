<?php

namespace Tests\Unit;

use App\Support\Principal;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use LogicException;
use PHPUnit\Framework\TestCase;

class PrincipalTest extends TestCase
{
    /** @return AccessToken<mixed> */
    private function token(?string $subject): AccessToken
    {
        return new AccessToken([
            'oauth_access_token_id' => 'tok1',
            'oauth_client_id' => 'client-uuid',
            'oauth_user_id' => $subject,
            'oauth_scopes' => ['tasks:read'],
        ]);
    }

    public function test_user_token_yields_a_user_principal(): void
    {
        $p = Principal::fromToken($this->token('42'));

        $this->assertSame('user', $p->type);
        $this->assertSame('user:42', $p->ownerKey());
        $this->assertSame('client-uuid', $p->clientId);
        $this->assertSame(['tasks:read'], $p->scopes);
    }

    public function test_client_credentials_token_yields_a_client_principal(): void
    {
        // JWT subject == client id (League) or absent: both mean "machine".
        foreach ([null, '', 'client-uuid'] as $subject) {
            $p = Principal::fromToken($this->token($subject));
            $this->assertSame('client', $p->type);
            $this->assertSame('client:client-uuid', $p->ownerKey());
        }
    }

    public function test_from_request_requires_the_middleware(): void
    {
        $this->expectException(LogicException::class);
        Principal::from(Request::create('/x'));
    }

    public function test_from_request_returns_the_attribute(): void
    {
        $p = Principal::fromToken($this->token('7'));
        $request = Request::create('/x');
        $request->attributes->set(Principal::ATTRIBUTE, $p);

        $this->assertSame($p, Principal::from($request));
    }
}
