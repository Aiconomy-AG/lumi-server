<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Sales\Database\Factories\ProductFactory;
use Modules\Sales\Enums\ShopifySyncStatus;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
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

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
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
