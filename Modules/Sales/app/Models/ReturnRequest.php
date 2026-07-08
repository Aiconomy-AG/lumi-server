<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    use HasFactory;

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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
