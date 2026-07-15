<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            return;
        }

        if (Schema::hasColumn('device_tokens', 'device_id')) {
            return;
        }

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->string('device_id')->nullable()->after('platform');
        });

        DB::table('device_tokens')
            ->whereNull('device_id')
            ->update(['device_id' => DB::raw('token')]);

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropUnique(['token']);
        });

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->unique(['user_id', 'platform', 'device_id']);
        });

        DB::statement('ALTER TABLE device_tokens MODIFY platform VARCHAR(32) NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_tokens') || ! Schema::hasColumn('device_tokens', 'device_id')) {
            return;
        }

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'platform', 'device_id']);
            $table->unique('token');
            $table->dropColumn('device_id');
        });

        DB::statement("ALTER TABLE device_tokens MODIFY platform ENUM('android', 'ios') NOT NULL");
    }
};
