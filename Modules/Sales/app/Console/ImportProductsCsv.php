<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use SplFileObject;

#[Signature('sales:import-products {path : Path to the products export CSV}')]
#[Description('Import products, variants and categories from a products export CSV')]
class ImportProductsCsv extends Command
{
    private array $columns = [];

    private array $categories = [];

    public function handle(ProductSyncService $shopify): int
    {
        $path = (string) $this->argument('path');

        if (! is_readable($path)) {
            $this->components->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);

        if ($rows === null) {
            return self::FAILURE;
        }

        [$weightGroups, $colourGroups] = $this->classifyRows($rows);

        $stats = ['products' => 0, 'variants' => 0];

        DB::transaction(function () use ($weightGroups, $colourGroups, &$stats) {
            foreach ($weightGroups as $group) {
                $this->importWeightProduct($group, $stats);
            }

            foreach ($colourGroups as $group) {
                $this->importColourProduct($group, $stats);
            }
        });

        $this->components->info(sprintf(
            'Imported %d products (%d variants, %d categories).',
            $stats['products'],
            $stats['variants'],
            count($this->categories),
        ));

//        $this->components->info('Deleting existing Shopify products...');
//        $deleted = $shopify->deleteAll();
//        $this->components->info(sprintf('Deleted %d Shopify products.', $deleted));
//
//        $this->components->info('Syncing products to Shopify...');
//        $shopify->seed();
//        $this->components->info('Shopify sync complete.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<int, string|null>>|null
     */
    private function readRows(string $path): ?array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = $file->fgetcsv();

        if (! is_array($header) || ! in_array('Product ID', $header, true)) {
            $this->components->error('File is missing the expected CSV header.');

            return null;
        }

        $this->columns = array_flip($header);

        $rows = [];

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $row === [null] || count($row) < count($this->columns)) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Split rows into two kinds of product groups:
     *  - Colour products: rows whose name ends in a colour code (e.g. "29N").
     *    Each of these rows has its own id/sku, so they are grouped by the
     *    shared base name (name with the colour code removed).
     *  - Weight products: everything else, grouped by Product ID. The first
     *    row of such a group is the product, the rest are weight variants.
     *
     * @param  array<int, array<int, string|null>>  $rows
     * @return array{0: array<string, array<int, array>>, 1: array<string, array<int, array>>}
     */
    private function classifyRows(array $rows): array
    {
        $weightGroups = [];
        $colourGroups = [];

        foreach ($rows as $row) {
            $name = $this->value($row, 'Name');

            if ($this->colourCode($name) !== null) {
                $colourGroups[$this->baseName($name)][] = $row;

                continue;
            }

            $weightGroups[$this->value($row, 'Product ID')][] = $row;
        }

        return [$weightGroups, $colourGroups];
    }

    /**
     * Rows sharing a Product ID form one product: the first row (which carries
     * the whole name) is the product itself, every following row is a weight
     * variant of it.
     */
    private function importWeightProduct(array $rows, array &$stats): void
    {
        $parent = $rows[0];
        $variantRows = array_slice($rows, 1);

        $product = Product::updateOrCreate(
            ['sku' => $this->value($parent, 'SKU')],
            [
                'name' => $this->value($parent, 'Name'),
                'description' => $this->description($parent),
                'price' => $this->productPrice($parent, $variantRows),
                'image_url' => $this->value($parent, 'Image URL') ?: null,
                'category_id' => $this->categoryId($parent),
            ],
        );

        $stats['products']++;

        // A product with no extra rows is a simple product; give it a single
        // default variant so it still has purchasable stock and price data.
        if ($variantRows === []) {
            $this->importWeightVariant($product, $parent, null);
            $stats['variants']++;

            return;
        }

        foreach ($variantRows as $row) {
            $this->importWeightVariant($product, $row, $this->parseWeight($this->value($row, 'Name')));
            $stats['variants']++;
        }
    }

    /**
     * Rows that share a base name but carry a colour code (e.g. "Slap Stick
     * 29N") are one product with a colour variant per row. Each row has its own
     * id/sku, so any of them can seed the product; we use the first one.
     */
    private function importColourProduct(array $rows, array &$stats): void
    {
        $parent = $rows[0];

        $product = Product::updateOrCreate(
            ['sku' => $this->value($parent, 'SKU')],
            [
                'name' => $this->baseName($this->value($parent, 'Name')),
                'description' => $this->description($parent),
                'price' => $this->productPrice($parent, $rows),
                'image_url' => $this->value($parent, 'Image URL') ?: null,
                'category_id' => $this->categoryId($parent),
            ],
        );

        $stats['products']++;

        foreach ($rows as $row) {
            $this->importColourVariant($product, $row);
            $stats['variants']++;
        }
    }

    /**
     * @param  array{weight: float, unit: ?string}|null  $weight
     */
    private function importWeightVariant(Product $product, array $row, ?array $weight): void
    {
        $sku = $this->variantSku($product, $row, $weight);

        ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'price' => $this->price($row),
                'weight' => $weight['weight'] ?? null,
                'weight_unit' => $weight['unit'] ?? null,
                'colour' => null,
                'stock_quantity' => max(0, (int) $this->value($row, 'Stock Quantity')),
            ],
        );
    }

    private function importColourVariant(Product $product, array $row): void
    {
        $sku = $this->variantSku($product, $row, null);

        ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'price' => $this->price($row),
                'weight' => null,
                'weight_unit' => null,
                'colour' => $this->colourCode($this->value($row, 'Name')),
                'stock_quantity' => max(0, (int) $this->value($row, 'Stock Quantity')),
            ],
        );
    }

    /**
     * Extract the weight that follows the last "-" or "," of a variant name,
     * e.g. "Sakura Shower Gel, 570g" => ['weight' => 570.0, 'unit' => 'g'] and
     * "Basma Body Scrub, 125" => ['weight' => 125.0, 'unit' => null]. The unit
     * stays null when the title does not spell one out.
     *
     * @return array{weight: float, unit: ?string}|null
     */
    private function parseWeight(string $name): ?array
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
     * The colour code is the trailing "<1-2 digits><N|W|C>" token of a name,
     * e.g. "Slap Stick 29N" => "29N". Returns null when there is none.
     */
    private function colourCode(string $name): ?string
    {
        return preg_match('/\s(\d{1,2}[NWC])$/', $name, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * The product name with its trailing colour code removed,
     * e.g. "Slap Stick 29N" => "Slap Stick".
     */
    private function baseName(string $name): string
    {
        return trim(preg_replace('/\s\d{1,2}[NWC]$/', '', $name));
    }

    /**
     * @param  array{weight: float, unit: ?string}|null  $weight
     */
    private function variantSku(Product $product, array $row, ?array $weight): string
    {
        $sku = $this->value($row, 'Variant SKU');

        if ($sku === '') {
            $sku = $this->value($row, 'SKU');

            if ($weight !== null) {
                $sku .= '-'.$this->weightLabel($weight);
            }
        }

        $owner = ProductVariant::where('sku', $sku)->value('product_id');

        if ($owner !== null && (int) $owner !== (int) $product->id) {
            $this->components->warn("Variant SKU {$sku} already in use, importing as {$sku}-p{$product->id}.");
            $sku .= "-p{$product->id}";
        }

        return $sku;
    }

    /**
     * @param  array{weight: float, unit: ?string}  $weight
     */
    private function weightLabel(array $weight): string
    {
        $value = rtrim(rtrim(number_format($weight['weight'], 2, '.', ''), '0'), '.');

        return $value.($weight['unit'] ?? '');
    }

    private function productPrice(array $parent, array $variantRows): float
    {
        $price = $this->price($parent);

        if ($price > 0 || $variantRows === []) {
            return $price;
        }

        $prices = array_filter(
            array_map(fn (array $row) => $this->price($row), $variantRows),
            fn (float $value) => $value > 0,
        );

        return $prices === [] ? $price : min($prices);
    }

    private function categoryId(array $row): ?int
    {
        $name = $this->value($row, 'Category');

        if ($name === '') {
            return null;
        }

        return $this->categories[$name] ??= Category::firstOrCreate(['name' => $name])->id;
    }

    private function price(array $row): float
    {
        return (float) ($this->value($row, 'Retail Price CHF (Gross)') ?: $this->value($row, 'Retail Price CHF'));
    }

    private function description(array $row): ?string
    {
        return $this->value($row, 'Description (de_CH)')
            ?: $this->value($row, 'Description (en)')
            ?: $this->value($row, 'Description')
            ?: null;
    }

    private function value(array $row, string $column): string
    {
        return trim((string) ($row[$this->columns[$column]] ?? ''));
    }
}
