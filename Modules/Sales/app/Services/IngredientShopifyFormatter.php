<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Collection;
use Modules\Sales\Models\Ingredients;

class IngredientShopifyFormatter
{

    public static function toMetafieldValue(Collection $ingredients, string $type): ?string
    {
        if ($ingredients->isEmpty()) {
            return null;
        }

        return match ($type) {
            'rich_text_field' => self::toRichText($ingredients),
            default => self::toHtml($ingredients),
        };
    }

    public static function toHtml(Collection $ingredients): string
    {
        $parts = $ingredients
            ->map(fn (Ingredients $ingredient) => self::formatHtmlIngredient($ingredient))
            ->all();

        return '<p>'.implode(', ', $parts).'</p>';
    }

    public static function toRichText(Collection $ingredients): string
    {
        $children = [];
        $first = true;

        foreach ($ingredients as $ingredient) {
            if (! $first) {
                $children[] = ['type' => 'text', 'value' => ', '];
            }

            $node = [
                'type' => 'text',
                'value' => self::displayName($ingredient),
            ];

            if ($ingredient->is_natural) {
                $node['bold'] = true;
            }

            $children[] = $node;
            $first = false;
        }

        return json_encode([
            'type' => 'root',
            'children' => [
                [
                    'type' => 'paragraph',
                    'children' => $children,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function formatHtmlIngredient(Ingredients $ingredient): string
    {
        $name = htmlspecialchars(self::displayName($ingredient), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $ingredient->is_natural ? "<strong>{$name}</strong>" : $name;
    }

    private static function displayName(Ingredients $ingredient): string
    {
        return $ingredient->is_allergen ? '*'.$ingredient->name : $ingredient->name;
    }
}
