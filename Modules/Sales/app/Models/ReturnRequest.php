<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'shop_domain',
        'shopify_customer_id',
        'shopify_order_id',
        'shopify_order_name',
        'email',
        'items',
        'reason',
        'notes',
        'status',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
