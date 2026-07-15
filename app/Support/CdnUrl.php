<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class CdnUrl
{
    public static function for(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk(config('media.disk'))->url($path);
    }

    public static function thumb(?string $path, int $width): ?string
    {
        $url = self::for($path);

        return $url === null ? null : $url.'?width='.$width;
    }
}
