<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Ingredients;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Services\IngredientListParser;
use Modules\Sales\Services\ProductVariantAttributeParser;
use SplFileObject;

#[Signature('sales:import-products {path : Path to the products export CSV}')]
#[Description('Import products, variants, categories and ingredients from a products export CSV')]
class ImportProductsCsv extends Command
{

    private const SKIP_RECORDS = [1260];

    private const LAST_RECORD = 1275;

    private array $columns = [];

    private array $categories = [];

    private array $ingredients = [];

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

        $stats = ['products' => 0, 'variants' => 0, 'ingredients' => 0];

        DB::transaction(function () use ($weightGroups, $colourGroups, &$stats) {
            foreach ($weightGroups as $group) {
                $this->importWeightProduct($this->sortProductGroup($group), $stats);
            }

            foreach ($colourGroups as $group) {
                $this->importColourProduct($this->sortProductGroup($group), $stats);
            }
        });

        $this->components->info(sprintf(
            'Imported %d products (%d variants, %d categories, %d ingredients).',
            $stats['products'],
            $stats['variants'],
            count($this->categories),
            $stats['ingredients'],
        ));

        AuditLog::recordSystem(
            module: 'sales',
            action: 'import',
            entityType: 'products',
            entityId: 0,
            label: 'CSV import batch',
            changes: ['new' => $stats],
            description: 'Products imported from CSV: '.$path,
            actorName: 'CSV Import',
        );

        $this->components->info('Queueing products for Shopify sync...');
        $queued = $shopify->queueAll();
        $this->components->info(sprintf(
            'Queued %d products. Ensure your Forge worker is running on the shopify-sync queue.',
            $queued,
        ));

        return self::SUCCESS;
    }

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
        $recordNumber = 1;

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $row === [null] || count($row) < count($this->columns)) {
                continue;
            }

            $recordNumber++;

            if ($this->isTestRecord($recordNumber)) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function isTestRecord(int $recordNumber): bool
    {
        return $recordNumber > self::LAST_RECORD
            || in_array($recordNumber, self::SKIP_RECORDS, true);
    }

    private function classifyRows(array $rows): array
    {
        $weightGroups = [];
        $colourGroups = [];

        foreach ($rows as $row) {
            $name = $this->value($row, 'Name');

            if (ProductVariantAttributeParser::colourCode($name) !== null) {
                $colourGroups[ProductVariantAttributeParser::baseName($name)][] = $row;

                continue;
            }

            $weightGroups[$this->value($row, 'Product ID')][] = $row;
        }

        return [$weightGroups, $colourGroups];
    }

    private function sortProductGroup(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $aIsVariant = $this->value($a, 'Variant ID') !== '';
            $bIsVariant = $this->value($b, 'Variant ID') !== '';

            if ($aIsVariant === $bIsVariant) {
                return 0;
            }

            return $aIsVariant <=> $bIsVariant;
        });

        return $rows;
    }

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

        $this->importIngredients($product, $parent, $stats);

        $stats['products']++;

        if ($variantRows === []) {
            $this->importVariant($product, $parent);
            $stats['variants']++;

            return;
        }

        foreach ($variantRows as $row) {
            $this->importVariant($product, $row);
            $stats['variants']++;
        }
    }

    private function importColourProduct(array $rows, array &$stats): void
    {
        $parent = $rows[0];

        $product = Product::updateOrCreate(
            ['sku' => $this->value($parent, 'SKU')],
            [
                'name' => ProductVariantAttributeParser::baseName($this->value($parent, 'Name')),
                'description' => $this->description($parent),
                'price' => $this->productPrice($parent, $rows),
                'image_url' => $this->value($parent, 'Image URL') ?: null,
                'category_id' => $this->categoryId($parent),
            ],
        );

        $this->importIngredients($product, $parent, $stats);

        $stats['products']++;

        foreach ($rows as $row) {
            $this->importColourVariant($product, $row);
            $stats['variants']++;
        }
    }

    private function importVariant(Product $product, array $row): void
    {
        $attributes = $this->variantAttributes($row);
        $sku = $this->variantSku($product, $row, $attributes);

        ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'name' => $this->variantName($row),
                'price' => $this->price($row),
                'weight' => $attributes['weight'],
                'weight_unit' => $attributes['unit'],
                'colour' => $attributes['colour'],
                'stock_quantity' => max(0, (int) $this->value($row, 'Stock Quantity')),
                'options' => ProductVariantAttributeParser::options($this->value($row, 'Variant Option Values')),
            ],
        );
    }

    private function importColourVariant(Product $product, array $row): void
    {
        $attributes = $this->variantAttributes($row);
        $attributes['colour'] = ProductVariantAttributeParser::colourCode($this->value($row, 'Name'));
        $sku = $this->variantSku($product, $row, $attributes);

        ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'name' => $this->variantName($row),
                'price' => $this->price($row),
                'weight' => $attributes['weight'],
                'weight_unit' => $attributes['unit'],
                'colour' => $attributes['colour'],
                'stock_quantity' => max(0, (int) $this->value($row, 'Stock Quantity')),
                'options' => ProductVariantAttributeParser::options($this->value($row, 'Variant Option Values')),
            ],
        );
    }

    private function variantName(array $row): ?string
    {
        $name = $this->value($row, 'Variant Name');

        if ($name === '') {
            $name = $this->value($row, 'Name');
        }

        return $name !== '' ? $name : null;
    }

    private function variantAttributes(array $row): array
    {
        return ProductVariantAttributeParser::fromRow(
            $this->value($row, 'Name'),
            $this->value($row, 'Color'),
            $this->value($row, 'Farbe'),
            $this->value($row, 'Size'),
            $this->value($row, 'Grösse'),
        );
    }

    private function variantSku(Product $product, array $row, array $attributes): string
    {
        $sku = $this->value($row, 'Variant SKU');

        if ($sku === '') {
            $sku = $this->value($row, 'SKU');
            $suffix = $this->variantSkuSuffix($attributes);

            if ($suffix !== null) {
                $sku .= '-'.$suffix;
            }
        }

        $owner = ProductVariant::where('sku', $sku)->value('product_id');

        if ($owner !== null && (int) $owner !== (int) $product->id) {
            $this->components->warn("Variant SKU {$sku} already in use, importing as {$sku}-p{$product->id}.");
            $sku .= "-p{$product->id}";
        }

        return $sku;
    }

    private function variantSkuSuffix(array $attributes): ?string
    {
        $parts = [];

        if ($attributes['colour'] !== null) {
            $parts[] = $attributes['colour'];
        }

        if ($attributes['weight'] !== null || $attributes['unit'] !== null) {
            $value = $attributes['weight'] !== null
                ? rtrim(rtrim(number_format($attributes['weight'], 2, '.', ''), '0'), '.')
                : '';
            $label = $value.($attributes['unit'] ?? '');

            if ($label !== '') {
                $parts[] = $label;
            }
        }

        $label = trim(implode('-', $parts));

        return $label !== '' ? $label : null;
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

    private function importIngredients(Product $product, array $row, array &$stats): void
    {
        $parsed = IngredientListParser::parse($this->value($row, 'Ingredients (de_CH)'));

        if ($parsed === []) {
            $product->ingredients()->detach();

            return;
        }

        $ingredientIds = [];

        foreach ($parsed as $ingredient) {
            $ingredientIds[] = $this->ingredientId($ingredient, $stats);
        }

        $product->ingredients()->sync($ingredientIds);
    }

    private function ingredientId(array $ingredient, array &$stats): int
    {
        $name = $ingredient['name'];

        if (isset($this->ingredients[$name])) {
            $this->mergeIngredientFlags($this->ingredients[$name], $ingredient);

            return $this->ingredients[$name];
        }

        $model = Ingredients::firstOrCreate(
            ['name' => $name],
            [
                'is_vegan' => false,
                'is_natural' => $ingredient['is_natural'],
                'is_allergen' => $ingredient['is_allergen'],
            ],
        );

        if ($model->wasRecentlyCreated) {
            $stats['ingredients']++;
        }

        $this->mergeIngredientFlags($model->getKey(), $ingredient);

        return $this->ingredients[$name] = $model->getKey();
    }

    private function mergeIngredientFlags(int $ingredientId, array $ingredient): void
    {
        $model = Ingredients::query()->find($ingredientId);

        if ($model === null) {
            return;
        }

        $isNatural = $model->is_natural || $ingredient['is_natural'];
        $isAllergen = $model->is_allergen || $ingredient['is_allergen'];

        if ($model->is_natural === $isNatural && $model->is_allergen === $isAllergen) {
            return;
        }

        $model->update([
            'is_natural' => $isNatural,
            'is_allergen' => $isAllergen,
            'is_vegan' => false,
        ]);
    }

    private function value(array $row, string $column): string
    {
        return trim((string) ($row[$this->columns[$column]] ?? ''));
    }
}
