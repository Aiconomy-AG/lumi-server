<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sales\Database\Factories\ProductVariantFactory;

// use Modules\Sales\Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'weight',
        'weight_unit',
        'stock_quantity',
        'colour',
        'options',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock_quantity' => 'integer',
        'options' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(
            CartItem::class,
            'product_variant_id'
        );
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            OrderItem::class,
            'product_variant_id'
        );
    }

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }
}
