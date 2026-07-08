<?php

return [
    'client_id' => env('SHOPIFY_ADMIN_ID'),
    'client_secret' => env('SHOPIFY_ADMIN_SECRET'),
    'shop' => env('SHOPIFY_SHOP'),
    'app_url' => env('SHOPIFY_APP_URL'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),

    /*
    |--------------------------------------------------------------------------
    | Local category → Shopify collection handle
    |--------------------------------------------------------------------------
    |
    | Maps local categories.id to the Shopify manual collection handle (URL
    | slug). Used when assigning synced products to collections.
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
];
