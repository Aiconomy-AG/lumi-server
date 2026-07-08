<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'shop_domain',
        'shopify_customer_id',
        'shopify_order_id',
        'shopify_order_name',
        'email',
        'items',
        'reason',
        'refund_amount',
        'received_at',
        'refunded_at',
        'notes',
        'status',
    ];

    protected $casts = [
        'items' => 'array',
        'refund_amount' => 'decimal:2',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
