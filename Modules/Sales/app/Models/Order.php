<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Order extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'customer_id',
        'shopify_order_id',
        'shopify_order_name',
        'shopify_customer_id',
        'status',
        'subtotal',
        'shipping_cost',
        'total_amount',
        'shipping_address',
        'payment_method',
        'payment_status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['quantity'])
            ->withTimestamps();
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function scopeWhereApiStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'shipped' => $query->where('payment_status', 'shipped'),
            'delivered' => $query->where('payment_status', 'fulfilled'),
            'cancelled' => $query->whereIn('status', ['expired', 'voided']),
            'processing' => $query
                ->whereIn('status', ['paid', 'partially_paid', 'authorized'])
                ->whereNotIn('payment_status', ['shipped', 'fulfilled']),
            default => $query
                ->whereNotIn('status', ['expired', 'voided', 'paid', 'partially_paid', 'authorized'])
                ->whereNotIn('payment_status', ['shipped', 'fulfilled']),
        };
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing('customer');

        return [
            'id' => (int) $this->id,
            'shopify_order_name' => $this->shopify_order_name,
            'customer_email' => $this->customer?->email,
            'customer_username' => $this->customer?->username,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }
}
