<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->string('shopify_product_id')->nullable()->unique();
            $table->enum('shopify_sync_status', ['synced', 'unsynced', 'syncing', 'error'])->nullable()->default('unsynced');
        });
    }

    public function down(): void {
        Schema::dropColumns('products', ['shopify_product_id', 'shopify_sync_status']);

    }
};
