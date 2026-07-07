<?php

return [
    'client_id' => env('SHOPIFY_ADMIN_ID'),
    'client_secret' => env('SHOPIFY_ADMIN_SECRET'),
    'sales' => env('SHOPIFY_SHOP'),
    'app_url' => env('SHOPIFY_APP_URL'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
];
