<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('seen_at');
            $table->index(['recipient_user_id', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropIndex(['recipient_user_id', 'dismissed_at']);
            $table->dropColumn('dismissed_at');
        });
    }
};
