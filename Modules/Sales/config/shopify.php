<?php

return [
    'client_id' => env('SHOPIFY_ADMIN_ID'),
    'client_secret' => env('SHOPIFY_ADMIN_SECRET'),
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
    'shop' => env('SHOPIFY_SHOP'),
    'app_url' => env('SHOPIFY_APP_URL'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'proxy_subpath' => env('SHOPIFY_PROXY_SUBPATH', 'lumi'),
    'publish_products' => env('SHOPIFY_PUBLISH_PRODUCTS', true),
    'online_store_publication_id' => env('SHOPIFY_ONLINE_STORE_PUBLICATION_ID'),

    'inventory_location_id' => env('SHOPIFY_INVENTORY_LOCATION_ID'),

    'category_collections' => [
        1 => 'bath',
        2 => 'shower',
        3 => 'gifts-co',
        4 => 'face',
        5 => 'hair',
        6 => 'body',
        7 => 'fragrance',
        8 => 'new',
        9 => 'limited',
    ],

    'ingredients_metafield' => [
        'namespace' => 'custom',
        'key' => 'ingredients',
        'type' => env('SHOPIFY_INGREDIENTS_METAFIELD_TYPE', 'rich_text_field'),
    ],
];
