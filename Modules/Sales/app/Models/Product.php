<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Sales\Database\Factories\ProductFactory;
use Modules\Sales\Enums\ShopifySyncStatus;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'image_url',
        'shopify_product_id',
        'shopify_sync_status',
        'category_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'shopify_sync_status' => ShopifySyncStatus::class,
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing('variants', 'category', 'ingredients');
        return [
            'id' => (int) $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'category_id' => $this->category_id !== null ? (int) $this->category_id : null,
            'category_name' => $this->category?->name,
            'ingredient_ids' => $this->ingredients
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),

            'ingredient_names' => $this->ingredients
                ->pluck('name')
                ->filter()
                ->values()
                ->all(),

            'is_vegan' => $this->ingredients->isNotEmpty()
                && $this->ingredients->every(
                    fn ($ingredient) => (bool) $ingredient->is_vegan
                ),

            'has_allergens' => $this->ingredients->contains(
                fn ($ingredient) => (bool) $ingredient->is_allergen
            ),

            'is_all_natural' => $this->ingredients->isNotEmpty()
                && $this->ingredients->every(
                    fn ($ingredient) => (bool) $ingredient->is_natural
                ),

            'is_available' => $this->variants->contains(
                fn (ProductVariant $variant) => (int) $variant->stock_quantity > 0
            ),

            'total_stock' => $this->variants->sum(
                fn (ProductVariant $variant) => (int) $variant->stock_quantity
            ),
            'variant_skus' => $this->variants
                ->pluck('sku')
                ->filter()
                ->values()
                ->all(),

            'variant_names' => $this->variants
                ->pluck('name')
                ->filter()
                ->values()
                ->all(),

            'variant_colours' => $this->variants
                ->pluck('colour')
                ->filter()
                ->unique()
                ->values()
                ->all(),

            'variant_options' => $this->variants
                ->pluck('options')
                ->filter()
                ->values()
                ->all(),

            'variant_weights' => $this->variants
                ->pluck('weight')
                ->filter(fn ($weight) => $weight !== null)
                ->map(fn ($weight) => (float) $weight)
                ->unique()
                ->values()
                ->all(),

            'variant_weight_units' => $this->variants
                ->pluck('weight_unit')
                ->filter()
                ->unique()
                ->values()
                ->all(),

            'variants' => $this->variants->map(function (ProductVariant $variant) {
                return [
                    'id' => (int) $variant->id,
                    'shopify_variant_id' => $variant->shopify_variant_id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'price' => (float) $variant->price,
                    'weight' => $variant->weight !== null
                        ? (float) $variant->weight
                        : null,
                    'weight_unit' => $variant->weight_unit,
                    'colour' => $variant->colour,
                    'options' => $variant->options,
                    'stock_quantity' => (int) $variant->stock_quantity,
                ];
            })->values()->all(),

            'updated_at' => $this->updated_at?->timestamp,

        ];
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function wishedByCustomers()
    {
        return $this->belongsToMany(Customer::class, 'wishlist_items')
            ->withTimestamps();
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredients::class, 'product_ingredients', 'product_id', 'ingredient_id')
            ->withTimestamps();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
