<?php

namespace Modules\Sales\Services;

class IngredientListParser
{
    /**
     * Parse an HTML ingredient list from the CSV "Ingredients (de_CH)" column.
     *
     * @return array<int, array{name: string, is_natural: bool, is_allergen: bool}>
     */
    public static function parse(string $html): array
    {
        $html = trim(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($html === '') {
            return [];
        }

        $ingredients = [];
        $inStrong = false;

        $parts = preg_split(
            '/(<\/?strong\b[^>]*>)/i',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if (! is_array($parts)) {
            return [];
        }

        foreach ($parts as $part) {
            if (preg_match('/^<strong\b/i', $part) === 1) {
                $inStrong = true;

                continue;
            }

            if (preg_match('/^<\/strong>/i', $part) === 1) {
                $inStrong = false;

                continue;
            }

            $text = strip_tags($part);

            foreach (explode(',', $text) as $chunk) {
                $name = self::normalizeName($chunk);

                if ($name === '') {
                    continue;
                }

                if (self::shouldMergeWithPrevious($name, $ingredients)) {
                    $lastIndex = count($ingredients) - 1;
                    $ingredients[$lastIndex]['name'] .= $name;

                    continue;
                }

                $isAllergen = str_starts_with($name, '*');

                if ($isAllergen) {
                    $name = trim(ltrim($name, '*'));
                }

                if ($name === '') {
                    continue;
                }

                $ingredients[] = [
                    'name' => $name,
                    'is_natural' => $inStrong,
                    'is_allergen' => $isAllergen,
                ];
            }
        }

        return $ingredients;
    }

    private static function normalizeName(string $name): string
    {
        $name = str_replace("\xc2\xa0", ' ', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));

        return is_string($name) ? $name : '';
    }

    /**
     * Handles malformed HTML such as "<strong>Sodium Laureth Sulfat</strong>e".
     *
     * @param  array<int, array{name: string, is_natural: bool, is_allergen: bool}>  $ingredients
     */
    private static function shouldMergeWithPrevious(string $name, array $ingredients): bool
    {
        if ($ingredients === []) {
            return false;
        }

        return strlen($name) <= 3 && preg_match('/^[\p{L}\p{N}]+$/u', $name) === 1;
    }
}
