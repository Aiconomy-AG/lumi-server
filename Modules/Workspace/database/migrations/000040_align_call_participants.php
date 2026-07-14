<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('call_participants', 'invited_at')) {
                $table->timestamp('invited_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('call_participants', 'ringing_delivered_at')) {
                $table->timestamp('ringing_delivered_at')->nullable()->after('invited_at');
            }

            if (! Schema::hasColumn('call_participants', 'joined_at')) {
                $table->timestamp('joined_at')->nullable()->after('ringing_delivered_at');
            }

            if (! Schema::hasColumn('call_participants', 'livekit_identity')) {
                $table->string('livekit_identity')->nullable()->after('joined_at');
            }
        });

        if (Schema::hasColumn('call_participants', 'answered_at')) {
            DB::table('call_participants')
                ->whereNotNull('answered_at')
                ->update(['joined_at' => DB::raw('answered_at')]);
        }

        DB::table('call_participants')
            ->whereNull('invited_at')
            ->update(['invited_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('call_participants', function (Blueprint $table): void {
            foreach (['invited_at', 'ringing_delivered_at', 'joined_at', 'livekit_identity'] as $column) {
                if (Schema::hasColumn('call_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
