<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_readable($path)) {
            $this->components->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $groups = $this->readProductGroups($path);

        if ($groups === null) {
            return self::FAILURE;
        }

        $stats = ['products' => 0, 'variants' => 0];

        DB::transaction(function () use ($groups, &$stats) {
            foreach ($groups as $rows) {
                $this->importProduct($rows, $stats);
            }
        });

        $this->components->info(sprintf(
            'Imported %d products (%d variants, %d categories).',
            $stats['products'],
            $stats['variants'],
            count($this->categories),
        ));

        return self::SUCCESS;
    }

    private function readProductGroups(string $path): ?array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = $file->fgetcsv();

        if (! is_array($header) || ! in_array('Product ID', $header, true)) {
            $this->components->error('File is missing the expected CSV header.');

            return null;
        }

        $this->columns = array_flip($header);

        $groups = [];

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $row === [null] || count($row) < count($this->columns)) {
                continue;
            }

            $groups[$this->value($row, 'Product ID')][] = $row;
        }

        return $groups;
    }

    private function importProduct(array $rows, array &$stats): void
    {
        $parent = $rows[0];
        $variantRows = array_values(array_filter(
            array_slice($rows, 1),
            fn (array $row) => $this->value($row, 'Variant SKU') !== '',
        ));

        $price = $this->price($parent);

        if ($price <= 0 && $variantRows !== []) {
            $price = min(array_map(fn (array $row) => $this->price($row), $variantRows));
        }

        $product = Product::updateOrCreate(
            ['sku' => $this->value($parent, 'SKU')],
            [
                'name' => $this->value($parent, 'Name'),
                'description' => $this->description($parent),
                'price' => $price,
                'image_url' => $this->value($parent, 'Image URL') ?: null,
                'category_id' => $this->categoryId($parent),
            ],
        );

        $stats['products']++;

        if ($variantRows === []) {
            $variantRows = [$parent];
        }

        foreach ($variantRows as $row) {
            $this->importVariant($product, $row);
            $stats['variants']++;
        }
    }

    private function importVariant(Product $product, array $row): void
    {
        $sku = $this->value($row, 'Variant SKU') ?: $this->value($row, 'SKU');

        $owner = ProductVariant::where('sku', $sku)->value('product_id');

        if ($owner !== null && (int) $owner !== (int) $product->id) {
            $this->components->warn("Variant SKU {$sku} already in use, importing as {$sku}-p{$product->id}.");
            $sku .= "-p{$product->id}";
        }

        ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'price' => $this->price($row),
                'weight' => (float) $this->value($row, 'Gewicht'),
                'weight_unit' => $this->value($row, 'Variant Weight Unit')
                    ?: $this->value($row, 'Weight Unit')
                    ?: 'g',
                'stock_quantity' => max(0, (int) $this->value($row, 'Stock Quantity')),
            ],
        );
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
