<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use LogicException;

/**
 * Who is calling: a user (authorization code) or a machine client (client credentials).
 * Built once per request by the `oauth` middleware; controllers never touch the raw token.
 */
final readonly class Principal
{
    public const ATTRIBUTE = 'oauth.principal';

    /** @param list<string> $scopes */
    public function __construct(
        public string $type,
        public string $id,
        public string $clientId,
        public string $tokenId,
        public array $scopes,
    ) {}

    /** @param AccessToken<mixed> $token */
    public static function fromToken(AccessToken $token): self
    {
        $clientId = (string) $token->oauth_client_id;
        $subject = (string) ($token->toArray()['oauth_user_id'] ?? '');
        // For client credentials the JWT subject is the client itself.
        $isClient = $subject === '' || $subject === $clientId;

        return new self(
            $isClient ? 'client' : 'user',
            $isClient ? $clientId : $subject,
            $clientId,
            (string) $token->oauth_access_token_id,
            array_values($token->oauth_scopes ?? []),
        );
    }

    public static function from(Request $request): self
    {
        $principal = $request->attributes->get(self::ATTRIBUTE);

        return $principal instanceof self
            ? $principal
            : throw new LogicException('Route is missing the "oauth" middleware.');
    }

    /** Key stored in tasks.owner: tenancy boundary of the demo resource. */
    public function ownerKey(): string
    {
        return $this->type.':'.$this->id;
    }
}
