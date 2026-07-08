<?php

namespace Modules\Sales\Tests\Unit\Services;

use Modules\Sales\Services\ProductVariantAttributeParser;
use PHPUnit\Framework\TestCase;

class ProductVariantAttributeParserTest extends TestCase
{
    public function test_it_parses_colour_and_size_from_three_part_name(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow('Daisy Daikon Deo, Blue, S');

        $this->assertSame('Blue', $attributes['colour']);
        $this->assertNull($attributes['weight']);
        $this->assertSame('s', $attributes['unit']);
    }

    public function test_it_parses_numeric_size_with_unit(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow('Daisy Daikon Deo, Red, 3XL');

        $this->assertSame('Red', $attributes['colour']);
        $this->assertSame(3.0, $attributes['weight']);
        $this->assertSame('xl', $attributes['unit']);
    }

    public function test_it_parses_weight_suffix_from_comma_name(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow('Sakura Shower Gel, 570g');

        $this->assertNull($attributes['colour']);
        $this->assertSame(570.0, $attributes['weight']);
        $this->assertSame('g', $attributes['unit']);
    }

    public function test_it_parses_weight_without_unit(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow('Basma Body Scrub, 125');

        $this->assertNull($attributes['colour']);
        $this->assertSame(125.0, $attributes['weight']);
        $this->assertNull($attributes['unit']);
    }

    public function test_it_falls_back_to_csv_columns(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow(
            'Daisy Daikon Deo, Blue, S',
            color: 'Blue',
            size: 'M',
        );

        $this->assertSame('Blue', $attributes['colour']);
        $this->assertNull($attributes['weight']);
        $this->assertSame('s', $attributes['unit']);
    }

    public function test_it_detects_trailing_colour_codes(): void
    {
        $this->assertSame('29N', ProductVariantAttributeParser::colourCode('Slap Stick 29N'));
        $this->assertSame('Slap Stick', ProductVariantAttributeParser::baseName('Slap Stick 29N'));
        $this->assertSame('14N', ProductVariantAttributeParser::colourCode('Trix Stick 14N'));
        $this->assertSame('Trix Stick', ProductVariantAttributeParser::baseName('Trix Stick 14N'));
    }

    public function test_it_parses_dash_weight_variants(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow('You Are My Sunshine - 210g');

        $this->assertNull($attributes['colour']);
        $this->assertSame(210.0, $attributes['weight']);
        $this->assertSame('g', $attributes['unit']);
    }

    public function test_it_parses_dash_weight_with_space_before_unit(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow('Keep It Fluffy - 90 g');

        $this->assertSame(90.0, $attributes['weight']);
        $this->assertSame('g', $attributes['unit']);
    }

    public function test_it_parses_volume_suffixes(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow("The Bee's Knees - 30ml");

        $this->assertSame(30.0, $attributes['weight']);
        $this->assertSame('ml', $attributes['unit']);
    }

    public function test_it_parses_kilogram_suffixes(): void
    {
        $attributes = ProductVariantAttributeParser::fromRow('You Are My Sunshine - 2kg');

        $this->assertSame(2.0, $attributes['weight']);
        $this->assertSame('kg', $attributes['unit']);
    }
}
