<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('username')->nullable()->change();
            $table->string('email')->nullable()->change();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('shopify_collection_id')->nullable()->unique()->after('name');
            $table->string('shopify_collection_handle')->nullable()->unique()->after('shopify_collection_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shopify_order_id')->nullable()->unique()->after('id');
            $table->string('shopify_order_name')->nullable()->after('shopify_order_id');
            $table->string('shopify_customer_id')->nullable()->index()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['shopify_order_id', 'shopify_order_name', 'shopify_customer_id']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['shopify_collection_id', 'shopify_collection_handle']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('username')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
