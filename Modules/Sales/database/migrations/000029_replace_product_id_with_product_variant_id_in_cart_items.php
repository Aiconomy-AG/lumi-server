<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('cart_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
        });

        /*
         * Migrate existing data here before dropping product_id,
         * if cart_items already contains records.
         */

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');

            $table->unique([
                'cart_id',
                'product_variant_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique([
                'cart_id',
                'product_variant_id',
            ]);

            $table->foreignId('product_id')
                ->nullable()
                ->after('cart_id')
                ->constrained('products')
                ->cascadeOnDelete();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};
