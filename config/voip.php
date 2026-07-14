<?php

return [
    'enabled' => (bool) env('VOIP_ENABLED', false),
    'ring_timeout_seconds' => (int) env('VOIP_RING_TIMEOUT_SECONDS', 45),
    'livekit' => [
        'url' => env('LIVEKIT_URL'),
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
        'token_ttl_seconds' => (int) env('LIVEKIT_TOKEN_TTL_SECONDS', 900),
        'webhook_secret' => env('LIVEKIT_WEBHOOK_SECRET'),
        'empty_timeout_seconds' => (int) env('LIVEKIT_EMPTY_TIMEOUT_SECONDS', 60),
        'max_participants_1v1' => (int) env('LIVEKIT_MAX_PARTICIPANTS_1V1', 2),
        'max_participants_group' => (int) env('LIVEKIT_MAX_PARTICIPANTS_GROUP', 10),
    ],
    'apns' => [
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID'),
        'key_path' => env('APNS_KEY_PATH'),
        'production' => (bool) env('APNS_PRODUCTION', false),
    ],
    'queues' => [
        'calls' => env('VOIP_CALLS_QUEUE', 'calls'),
    ],
    'cleanup' => [
        'device_token_days' => (int) env('VOIP_DEVICE_TOKEN_PRUNE_DAYS', 90),
        'orphaned_ringing_minutes' => (int) env('VOIP_ORPHANED_RINGING_MINUTES', 5),
        'call_events_days' => (int) env('VOIP_CALL_EVENTS_PRUNE_DAYS', 90),
    ],
];
