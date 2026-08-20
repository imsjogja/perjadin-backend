<?php

return [
    'base_url' => env('SIKKEPO_BASE_URL', 'https://sikkepo.dev-ims.net'),
    'platform_client_id' => env('SIKKEPO_PLATFORM_CLIENT_ID'),
    'platform_client_secret' => env('SIKKEPO_PLATFORM_CLIENT_SECRET'),
    'timeout' => (int) env('SIKKEPO_TIMEOUT_SECONDS', 20),
    'connect_timeout' => (int) env('SIKKEPO_CONNECT_TIMEOUT_SECONDS', 10),
    'retry_times' => (int) env('SIKKEPO_RETRY_TIMES', 2),
    'retry_sleep_ms' => (int) env('SIKKEPO_RETRY_SLEEP_MS', 300),
    'token_cache_key' => env('SIKKEPO_TOKEN_CACHE_KEY', 'sikkepo.platform.access_token'),
    'token_ttl_seconds' => (int) env('SIKKEPO_TOKEN_TTL_SECONDS', 300),
    'token_refresh_margin_seconds' => (int) env('SIKKEPO_TOKEN_REFRESH_MARGIN_SECONDS', 30),
];
