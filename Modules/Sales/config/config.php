<?php

return [
    'name' => 'Sales',
    'shopify' => [
        'client_secret' => env('SHOPIFY_ADMIN_SECRET'),
        'returns_client_secret' => env('SHOPIFY_RETURNS_APP_SECRET'),
    ],
];
