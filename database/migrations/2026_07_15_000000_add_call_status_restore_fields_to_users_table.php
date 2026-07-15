<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('call_status_restore_status')->nullable()->after('status');
            $table->uuid('call_status_restore_call_id')->nullable()->after('call_status_restore_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['call_status_restore_status', 'call_status_restore_call_id']);
        });
    }
};
