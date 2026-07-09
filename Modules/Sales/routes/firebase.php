<?php

return [

    'default' => env('FIREBASE_PROJECT', 'app'),

    'projects' => [

        'app' => [

            'credentials' => env('FIREBASE_CREDENTIALS'),

        ],

    ],

];
