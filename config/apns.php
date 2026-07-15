<?php

$useSandbox = filter_var(env('APNS_USE_SANDBOX', true), FILTER_VALIDATE_BOOL);

return [
    'p8_path' => env('APNS_P8_PATH'),
    'key_id' => env('APNS_KEY_ID'),
    'team_id' => env('APNS_TEAM_ID'),
    'bundle_id' => env('APNS_BUNDLE_ID', 'com.aico.lumi'),
    'use_sandbox' => $useSandbox,
    'voip_topic' => env('APNS_BUNDLE_ID', 'com.aico.lumi').'.voip',
    'host' => $useSandbox
        ? 'https://api.sandbox.push.apple.com'
        : 'https://api.push.apple.com',
    'jwt_ttl_seconds' => 3000,
];
