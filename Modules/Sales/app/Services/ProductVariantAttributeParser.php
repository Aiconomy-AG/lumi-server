<?php

namespace Modules\Sales\Services;

class ProductVariantAttributeParser
{
    /**
     * @return array{weight: ?float, unit: ?string, colour: ?string}
     */
    public static function fromRow(
        string $name,
        string $color = '',
        string $farbe = '',
        string $size = '',
        string $grosse = '',
    ): array {
        $parts = array_map('trim', explode(',', $name));

        $colour = null;
        $weight = null;
        $unit = null;

        if (count($parts) >= 3) {
            $colour = $parts[1] !== '' ? $parts[1] : null;
            [$weight, $unit] = self::parseSize($parts[2]);
        } elseif (count($parts) === 2) {
            $parsedWeight = self::parseWeight($name);

            if ($parsedWeight !== null) {
                $weight = $parsedWeight['weight'];
                $unit = $parsedWeight['unit'];
            } else {
                $colour = $parts[1] !== '' ? $parts[1] : null;
            }
        } else {
            $parsedWeight = self::parseWeight($name);

            if ($parsedWeight !== null) {
                $weight = $parsedWeight['weight'];
                $unit = $parsedWeight['unit'];
            }
        }

        if ($colour === null || $colour === '') {
            $colour = $color !== '' ? $color : ($farbe !== '' ? $farbe : null);
        }

        if ($weight === null && $unit === null) {
            $sizeValue = $size !== '' ? $size : ($grosse !== '' ? $grosse : null);

            if ($sizeValue !== null) {
                [$weight, $unit] = self::parseSize($sizeValue);
            }
        }

        return [
            'weight' => $weight,
            'unit' => $unit,
            'colour' => $colour,
        ];
    }

    /**
     * @return array{weight: float, unit: ?string}|null
     */
    public static function parseWeight(string $name): ?array
    {
        $dash = strrpos($name, '-');
        $comma = strrpos($name, ',');

        $position = false;

        if ($dash !== false) {
            $position = $dash;
        }

        if ($comma !== false && ($position === false || $comma > $position)) {
            $position = $comma;
        }

        if ($position === false) {
            return null;
        }

        $suffix = trim(substr($name, $position + 1));

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([\p{L}]+)?$/u', $suffix, $matches)) {
            return null;
        }

        return [
            'weight' => (float) $matches[1],
            'unit' => ($matches[2] ?? '') !== '' ? strtolower($matches[2]) : null,
        ];
    }

    /**
     * @return array{0: ?float, 1: ?string}
     */
    public static function parseSize(string $size): array
    {
        if ($size === '') {
            return [null, null];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*([\p{L}]+)?$/u', $size, $matches)) {
            return [
                (float) $matches[1],
                ($matches[2] ?? '') !== '' ? strtolower($matches[2]) : null,
            ];
        }

        return [null, strtolower($size)];
    }

    public static function colourCode(string $name): ?string
    {
        return preg_match('/\s(\d{1,2}[NWC])$/', $name, $matches) === 1
            ? $matches[1]
            : null;
    }

    public static function baseName(string $name): string
    {
        return trim(preg_replace('/\s\d{1,2}[NWC]$/', '', $name));
    }
}
