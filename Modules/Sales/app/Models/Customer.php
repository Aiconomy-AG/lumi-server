<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'username',
        'email',
        'shopify_customer_id'
    ];

    public function orders(){
        return $this->hasMany(Order::class);
    }

    public function wishlistItems() {
        return $this->hasMany(WishlistItem::class);
    }

    public function wishlistProducts(){
        return $this->belongsToMany(Product::class, 'wishlist_items')->withTimestamps();
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }
}
