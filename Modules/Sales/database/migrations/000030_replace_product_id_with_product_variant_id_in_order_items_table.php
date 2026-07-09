<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');

            $table->foreignId('product_variant_id')
                ->after('order_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->decimal('unit_price', 10, 2)
                ->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);

            $table->dropColumn([
                'product_variant_id',
                'unit_price',
            ]);

            $table->foreignId('product_id')
                ->after('order_id')
                ->constrained('products')
                ->restrictOnDelete();
        });
    }
};
