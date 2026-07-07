<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer("user_id");
            $table->enum("status", ["paid",
"pending",
"authorized",
"partially_paid",
"partially_refunded",
"refunded",
"voided",
"expired"]);
            $table->decimal("subtotal", 10);
            $table->decimal("shipping_cart", 10);
            $table->decimal("total_amount",10);
            $table->string("shipping_address");
            $table->string("payment_method");
            $table->enum("payment_status", ["unshipped",
            $table->timestamps(),
"shipped",
"fulfilled",
"partial",
"scheduled",
"on_hold",
"unfulfilled",
"request_declined"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
