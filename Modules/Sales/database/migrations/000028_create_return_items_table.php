<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('return_request_id')
                ->constrained('return_requests')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamps();

            $table->unique([
                'return_request_id',
                'order_item_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
