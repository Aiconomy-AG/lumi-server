<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'username',
        'email',
        'shopify_customer_id',
    ];

    /**
     * Resolve Customer from the authenticated User.
     */
    public static function resolveFromUser($user): self
    {
        return self::firstOrCreate(
            ['email' => $user->email],
            [
                'username' => $user->name,
                'shopify_customer_id' => 'mock_cus_' . uniqid(),
            ]
        );
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function wishlistProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlist_items')->withTimestamps();
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }
}
