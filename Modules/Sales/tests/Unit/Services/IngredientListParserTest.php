<?php

namespace Modules\Sales\Tests\Unit\Services;

use Modules\Sales\Services\IngredientListParser;
use PHPUnit\Framework\TestCase;

class IngredientListParserTest extends TestCase
{
    public function test_it_parses_block_strong_sections(): void
    {
        $html = '<p><strong>Glycerin, Spiraea Ulmaria Extract (Mädesüß-Extrakt), Glycine Soja Extract (Seidentofu), </strong>'
            .'Sodium Cocoyl Isethionate, <strong>Agave Tequilana Stem Extract (Bio Agavensirup), Vitis Vinifera Juice (frischer Traubensaft), </strong>'
            .'Cetrimonium Chloride, Parfum, <strong>*Citral</strong>, Coumarin, <strong>*Limonene, *Linalool</strong></p>';

        $ingredients = IngredientListParser::parse($html);

        $this->assertSame('Glycerin', $ingredients[0]['name']);
        $this->assertTrue($ingredients[0]['is_natural']);
        $this->assertFalse($ingredients[0]['is_allergen']);

        $this->assertSame('Sodium Cocoyl Isethionate', $ingredients[3]['name']);
        $this->assertFalse($ingredients[3]['is_natural']);
        $this->assertFalse($ingredients[3]['is_allergen']);

        $citral = collect($ingredients)->firstWhere('name', 'Citral');
        $this->assertNotNull($citral);
        $this->assertTrue($citral['is_natural']);
        $this->assertTrue($citral['is_allergen']);

        $limonene = collect($ingredients)->firstWhere('name', 'Limonene');
        $this->assertNotNull($limonene);
        $this->assertTrue($limonene['is_allergen']);

        $coumarin = collect($ingredients)->firstWhere('name', 'Coumarin');
        $this->assertNotNull($coumarin);
        $this->assertFalse($coumarin['is_natural']);
        $this->assertFalse($coumarin['is_allergen']);
    }

    public function test_it_parses_per_ingredient_strong_tags_from_csv(): void
    {
        $html = '<p><strong>Sodium Bicarbonate</strong>, <strong>Potassium Bitartrate (Weinstein)</strong>, '
            .'<strong>Sodium Laureth Sulfat</strong>e, Lauryl Betaine, <strong>*Eugenol</strong>, <strong>*Limonene</strong>, Parfum</p>';

        $ingredients = IngredientListParser::parse($html);

        $bicarbonate = collect($ingredients)->firstWhere('name', 'Sodium Bicarbonate');
        $this->assertTrue($bicarbonate['is_natural']);
        $this->assertFalse($bicarbonate['is_allergen']);

        $sulfate = collect($ingredients)->firstWhere('name', 'Sodium Laureth Sulfate');
        $this->assertNotNull($sulfate);
        $this->assertTrue($sulfate['is_natural']);

        $betaine = collect($ingredients)->firstWhere('name', 'Lauryl Betaine');
        $this->assertFalse($betaine['is_natural']);

        $eugenol = collect($ingredients)->firstWhere('name', 'Eugenol');
        $this->assertTrue($eugenol['is_natural']);
        $this->assertTrue($eugenol['is_allergen']);

        $parfum = collect($ingredients)->firstWhere('name', 'Parfum');
        $this->assertFalse($parfum['is_natural']);
    }
}
