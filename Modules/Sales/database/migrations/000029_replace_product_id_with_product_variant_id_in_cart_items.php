<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cart_items', 'product_variant_id')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->after('cart_id')
                    ->constrained('product_variants')
                    ->cascadeOnDelete();
            });
        }

        /*
         * Migrate existing data here before dropping product_id,
         * if cart_items already contains records.
         */

        if (Schema::hasColumn('cart_items', 'product_id')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                if (Schema::hasForeignKey('cart_items', ['product_id'])) {
                    $table->dropForeign(['product_id']);
                }

                $table->dropColumn('product_id');
            });
        }

        if (! Schema::hasIndex('cart_items', ['cart_id', 'product_variant_id'], 'unique')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->unique([
                    'cart_id',
                    'product_variant_id',
                ]);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('cart_items', ['cart_id', 'product_variant_id'], 'unique')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->dropUnique([
                    'cart_id',
                    'product_variant_id',
                ]);
            });
        }

        if (! Schema::hasColumn('cart_items', 'product_id')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->foreignId('product_id')
                    ->nullable()
                    ->after('cart_id')
                    ->constrained('products')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('cart_items', 'product_variant_id')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                if (Schema::hasForeignKey('cart_items', ['product_variant_id'])) {
                    $table->dropForeign(['product_variant_id']);
                }

                $table->dropColumn('product_variant_id');
            });
        }
    }
};
