<?php

// Only the keys we override; the rest comes from the package defaults. Dev dependency: see Makefile target `openapi`.
return [
    'api_path' => 'api',
    'info' => [
        'version' => '1.0.0',
        'description' => <<<'MD'
OAuth2-protected tasks API (example project). Obtain tokens at `POST /oauth/token` (client credentials,
authorization code + PKCE, refresh token) and send them as `Authorization: Bearer <token>`.
Scopes: `tasks:read`, `tasks:write` (write does not imply read). Errors use RFC 9457 `application/problem+json`.
The OAuth2 endpoints themselves (`/oauth/*`) are provided by Laravel Passport and follow RFC 6749.
MD,
    ],
];
