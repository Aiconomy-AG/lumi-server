<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('calls', 'mode')) {
                $table->string('mode', 16)->default('1v1')->after('destination_type');
            }

            if (! Schema::hasColumn('calls', 'type')) {
                $table->string('type', 16)->default('audio')->after('mode');
            }

            if (! Schema::hasColumn('calls', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('answered_at');
            }
        });

        if (Schema::hasColumn('calls', 'conversation_id')) {
            Schema::table('calls', function (Blueprint $table): void {
                $table->unsignedBigInteger('conversation_id')->nullable()->change();
            });
        }

        DB::table('calls')->whereNotNull('media_type')->update([
            'type' => DB::raw('media_type'),
        ]);

        DB::table('calls')->update([
            'mode' => DB::raw("CASE WHEN conversation_id IS NULL THEN '1v1' ELSE '1v1' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            foreach (['mode', 'type', 'started_at'] as $column) {
                if (Schema::hasColumn('calls', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
