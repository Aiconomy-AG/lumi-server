<?php

return [
    'enabled' => (bool) env('VOIP_ENABLED', false),
    'ring_timeout_seconds' => (int) env('VOIP_RING_TIMEOUT_SECONDS', 45),
    'livekit' => [
        'url' => env('LIVEKIT_URL'),
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
        'token_ttl_seconds' => (int) env('LIVEKIT_TOKEN_TTL_SECONDS', 900),
    ],
];
