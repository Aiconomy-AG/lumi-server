<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('call_participants', 'client_instance_id')) {
                $table->string('client_instance_id', 100)->nullable()->after('livekit_identity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_participants', function (Blueprint $table): void {
            if (Schema::hasColumn('call_participants', 'client_instance_id')) {
                $table->dropColumn('client_instance_id');
            }
        });
    }
};
