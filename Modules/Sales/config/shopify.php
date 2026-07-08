<?php

return [
    'client_id' => env('SHOPIFY_ADMIN_ID'),
    'client_secret' => env('SHOPIFY_ADMIN_SECRET'),
    'shop' => env('SHOPIFY_SHOP'),
    'app_url' => env('SHOPIFY_APP_URL'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'proxy_subpath' => env('SHOPIFY_PROXY_SUBPATH', 'lumi'),
    'publish_products' => env('SHOPIFY_PUBLISH_PRODUCTS', true),
    'online_store_publication_id' => env('SHOPIFY_ONLINE_STORE_PUBLICATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Inventory location
    |--------------------------------------------------------------------------
    |
    | Shopify location GID used when pushing variant stock quantities, e.g.
    | gid://shopify/Location/123456789. When omitted, the first active
    | location returned by the Admin API is used.
    |
    */
    'inventory_location_id' => env('SHOPIFY_INVENTORY_LOCATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Legacy local category → Shopify collection handle
    |--------------------------------------------------------------------------
    |
    | Optional fallback only. The sync now stores Shopify collection IDs and
    | handles on categories directly, but existing configured maps still work.
    |
    */
    'category_collections' => [
        1 => 'bath',           // Baden → Bath
        2 => 'shower',         // Duschen → Shower
        3 => 'gifts-co',       // Geschenke & Co. → Gifts & Co.
        4 => 'face',           // Gesicht → Face
        5 => 'hair',           // Haare → Hair
        6 => 'body',           // Körper → Body
        7 => 'fragrance',      // Düfte → Fragrance
        8 => 'new',            // New → New
        9 => 'limited',        // Limited → Limited
    ],

    /*
    |--------------------------------------------------------------------------
    | Product ingredients metafield
    |--------------------------------------------------------------------------
    */
    'ingredients_metafield' => [
        'namespace' => 'custom',
        'key' => 'ingredients',
        'type' => env('SHOPIFY_INGREDIENTS_METAFIELD_TYPE', 'rich_text_field'),
    ],
];
