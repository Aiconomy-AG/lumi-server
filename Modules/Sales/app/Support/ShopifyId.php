<?php

namespace Modules\Sales\Support;

class ShopifyId
{
    public static function numeric(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\/(\d+)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return preg_match('/^\d+$/', $value) === 1 ? $value : $value;
    }

    public static function productGid(?string $value): ?string
    {
        return self::gid('Product', $value);
    }

    public static function orderGid(?string $value): ?string
    {
        return self::gid('Order', $value);
    }

    public static function customerGid(?string $value): ?string
    {
        return self::gid('Customer', $value);
    }

    private static function gid(string $type, ?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'gid://shopify/')) {
            return $value;
        }

        $numeric = self::numeric($value);

        return $numeric === null ? null : "gid://shopify/{$type}/{$numeric}";
    }
}
