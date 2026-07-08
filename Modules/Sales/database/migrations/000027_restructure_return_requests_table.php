<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'shop_domain',
                'shopify_customer_id',
                'shopify_order_id',
                'shopify_order_name',
                'email',
                'items',
                'notes',
            ]);
        });

        Schema::table('return_requests', function (Blueprint $table): void {
            $table->foreignId('order_id')
                ->after('id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->decimal('refund_amount', 10, 2)
                ->default(0)
                ->after('reason');

            $table->timestamp('received_at')
                ->nullable()
                ->after('refund_amount');

            $table->timestamp('refunded_at')
                ->nullable()
                ->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);

            $table->dropColumn([
                'order_id',
                'refund_amount',
                'received_at',
                'refunded_at',
            ]);
        });

        Schema::table('return_requests', function (Blueprint $table): void {
            $table->string('shop_domain')->nullable();
            $table->string('shopify_customer_id')->nullable()->index();
            $table->string('shopify_order_id')->nullable()->index();
            $table->string('shopify_order_name')->nullable()->index();
            $table->string('email')->nullable();
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
        });
    }
};
