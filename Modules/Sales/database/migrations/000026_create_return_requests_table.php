<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shop_domain')->nullable();
            $table->string('shopify_customer_id')->nullable()->index();
            $table->string('shopify_order_id')->nullable()->index();
            $table->string('shopify_order_name')->nullable()->index();
            $table->string('email')->nullable();
            $table->json('items')->nullable();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->enum('status', ['requested', 'approved', 'rejected', 'received', 'refunded'])->default('requested');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
