<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('return_requests', 'shop_domain')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->string('shop_domain')->nullable()->after('customer_id');
            });
        }

        if (! Schema::hasColumn('return_requests', 'shopify_customer_id')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->string('shopify_customer_id')->nullable()->after('shop_domain');
            });
        }

        if (! Schema::hasIndex('return_requests', ['shopify_customer_id'])) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->index('shopify_customer_id');
            });
        }

        if (! Schema::hasColumn('return_requests', 'shopify_order_id')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->string('shopify_order_id')->nullable()->after('shopify_customer_id');
            });
        }

        if (! Schema::hasIndex('return_requests', ['shopify_order_id'])) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->index('shopify_order_id');
            });
        }

        if (! Schema::hasColumn('return_requests', 'shopify_order_name')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->string('shopify_order_name')->nullable()->after('shopify_order_id');
            });
        }

        if (! Schema::hasIndex('return_requests', ['shopify_order_name'])) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->index('shopify_order_name');
            });
        }

        if (! Schema::hasColumn('return_requests', 'email')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->string('email')->nullable()->after('shopify_order_name');
            });
        }

        if (! Schema::hasColumn('return_requests', 'items')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->json('items')->nullable()->after('email');
            });
        }

        if (! Schema::hasColumn('return_requests', 'notes')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->text('notes')->nullable()->after('reason');
            });
        }

        if (Schema::hasColumn('return_requests', 'order_id')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->unsignedBigInteger('order_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {

    }
};
