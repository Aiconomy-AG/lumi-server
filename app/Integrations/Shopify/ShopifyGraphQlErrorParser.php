<?php

namespace App\Integrations\Shopify;

class ShopifyGraphQlErrorParser
{
    /**
     * @param  array<int, mixed>  $errors
     */
    public static function messageFromErrors(array $errors): string
    {
        $formatted = self::formatErrors($errors);

        if ($formatted === '') {
            return 'Shopify GraphQL request returned errors.';
        }

        return 'Shopify GraphQL request returned errors: '.$formatted;
    }

    /**
     * @param  array<int, mixed>  $errors
     */
    public static function formatErrors(array $errors): string
    {
        $parts = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            $parts[] = self::formatSingleError($error);
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<int, mixed>  $errors
     */
    public static function formatErrorsAsJson(array $errors): string
    {
        $safeErrors = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            $safeErrors[] = self::sanitizeErrorArray($error);
        }

        return json_encode($safeErrors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private static function formatSingleError(array $error): string
    {
        $message = is_string($error['message'] ?? null) ? self::sanitize($error['message']) : 'Unknown error';

        $extensions = is_array($error['extensions'] ?? null) ? $error['extensions'] : [];
        $code = $extensions['code'] ?? null;

        $part = is_string($code) && $code !== '' ? "[{$code}] {$message}" : $message;

        if (isset($error['path']) && is_array($error['path']) && $error['path'] !== []) {
            $path = implode('.', array_map(strval(...), $error['path']));
            $part .= " (path: {$path})";
        }

        if (isset($error['locations']) && is_array($error['locations']) && $error['locations'] !== []) {
            $location = $error['locations'][0] ?? null;

            if (is_array($location)) {
                $line = $location['line'] ?? '?';
                $column = $location['column'] ?? '?';
                $part .= " (line {$line}, column {$column})";
            }
        }

        return $part;
    }

    /**
     * @param  array<string, mixed>  $error
     * @return array<string, mixed>
     */
    private static function sanitizeErrorArray(array $error): array
    {
        $safe = [];

        foreach ($error as $key => $value) {
            if ($key === 'message' && is_string($value)) {
                $safe[$key] = self::sanitize($value);

                continue;
            }

            if ($key === 'extensions' && is_array($value)) {
                $safe[$key] = self::sanitizeErrorArray($value);

                continue;
            }

            if (is_string($value)) {
                $safe[$key] = self::sanitize($value);

                continue;
            }

            if (is_array($value)) {
                $safe[$key] = array_is_list($value)
                    ? array_map(
                        fn ($item) => is_array($item) ? self::sanitizeErrorArray($item) : $item,
                        $value,
                    )
                    : self::sanitizeErrorArray($value);

                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }

    public static function sanitize(string $text): string
    {
        $sanitized = preg_replace('/shpat_[\w-]+|shpss_[\w-]+/i', '[redacted]', $text);

        return is_string($sanitized) ? $sanitized : $text;
    }
}
