<?php

return [

    'disk' => env('MEDIA_DISK', 'wasabi'),

    'avatar_max_kb' => (int) env('MEDIA_AVATAR_MAX_KB', 4096),

    'image_max_kb' => (int) env('MEDIA_IMAGE_MAX_KB', 10240),

];
