<?php

namespace Modules\Sales\Tests\Unit\Services;

use Illuminate\Support\Collection;
use Modules\Sales\Models\Ingredients;
use Modules\Sales\Services\IngredientShopifyFormatter;
use PHPUnit\Framework\TestCase;

class IngredientShopifyFormatterTest extends TestCase
{
    public function test_it_formats_html_with_strong_and_allergen_prefix(): void
    {
        $html = IngredientShopifyFormatter::toHtml(collect([
            $this->ingredient('Glycerin', isNatural: true),
            $this->ingredient('Sodium Cocoyl Isethionate'),
            $this->ingredient('Citral', isNatural: true, isAllergen: true),
        ]));

        $this->assertSame(
            '<p><strong>Glycerin</strong>, Sodium Cocoyl Isethionate, <strong>*Citral</strong></p>',
            $html,
        );
    }

    public function test_it_formats_rich_text_json_with_bold_natural_ingredients(): void
    {
        $json = IngredientShopifyFormatter::toRichText(collect([
            $this->ingredient('Glycerin', isNatural: true),
            $this->ingredient('Parfum'),
            $this->ingredient('Limonene', isNatural: true, isAllergen: true),
        ]));

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $children = $decoded['children'][0]['children'];

        $this->assertSame('Glycerin', $children[0]['value']);
        $this->assertTrue($children[0]['bold']);
        $this->assertSame('Parfum', $children[2]['value']);
        $this->assertArrayNotHasKey('bold', $children[2]);
        $this->assertSame('*Limonene', $children[4]['value']);
        $this->assertTrue($children[4]['bold']);
    }

    private function ingredient(string $name, bool $isNatural = false, bool $isAllergen = false): Ingredients
    {
        return new Ingredients([
            'name' => $name,
            'is_natural' => $isNatural,
            'is_allergen' => $isAllergen,
            'is_vegan' => false,
        ]);
    }
}
