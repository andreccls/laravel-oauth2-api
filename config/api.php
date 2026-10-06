<?php

return [
    // Requests per minute per OAuth client (Redis-backed in production).
    'rate_limit' => (int) env('API_RATE_LIMIT', 120),

    // Access tokens are short-lived; refresh tokens do the long-lived work.
    'access_token_ttl_minutes' => (int) env('API_ACCESS_TOKEN_TTL_MINUTES', 60),
    'refresh_token_ttl_days' => (int) env('API_REFRESH_TOKEN_TTL_DAYS', 14),
];
