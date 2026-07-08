<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('return_requests', 'order_id')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->foreignId('order_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('orders')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('return_requests', 'refund_amount')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->decimal('refund_amount', 10, 2)
                    ->default(0)
                    ->after('reason');
            });
        }

        if (! Schema::hasColumn('return_requests', 'received_at')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->timestamp('received_at')
                    ->nullable()
                    ->after('refund_amount');
            });
        }

        if (! Schema::hasColumn('return_requests', 'refunded_at')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                $table->timestamp('refunded_at')
                    ->nullable()
                    ->after('received_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('return_requests', 'order_id')) {
            Schema::table('return_requests', function (Blueprint $table): void {
                if (Schema::hasForeignKey('return_requests', ['order_id'])) {
                    $table->dropForeign(['order_id']);
                }

                $table->dropColumn('order_id');
            });
        }

        foreach (['refund_amount', 'received_at', 'refunded_at'] as $column) {
            if (Schema::hasColumn('return_requests', $column)) {
                Schema::table('return_requests', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
