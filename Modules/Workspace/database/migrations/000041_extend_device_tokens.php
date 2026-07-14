<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('device_tokens', 'device_id')) {
                $table->string('device_id')->nullable()->after('platform');
            }
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

        DB::statement("ALTER TABLE device_tokens MODIFY platform VARCHAR(32) NOT NULL");
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'platform', 'device_id']);
            $table->unique('token');

            if (Schema::hasColumn('device_tokens', 'device_id')) {
                $table->dropColumn('device_id');
            }
        });

        DB::statement("ALTER TABLE device_tokens MODIFY platform ENUM('android', 'ios') NOT NULL");
    }
};
