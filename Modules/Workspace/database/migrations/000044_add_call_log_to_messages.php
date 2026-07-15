<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'message_type')) {
                $table->string('message_type', 16)->default('text')->after('sender_id');
            }

            if (! Schema::hasColumn('messages', 'call_id')) {
                $table->uuid('call_id')->nullable()->after('message_type');
                $table->foreign('call_id')->references('id')->on('calls')->nullOnDelete();
                $table->unique('call_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (Schema::hasColumn('messages', 'call_id')) {
                $table->dropForeign(['call_id']);
                $table->dropUnique(['call_id']);
                $table->dropColumn('call_id');
            }

            if (Schema::hasColumn('messages', 'message_type')) {
                $table->dropColumn('message_type');
            }
        });
    }
};
