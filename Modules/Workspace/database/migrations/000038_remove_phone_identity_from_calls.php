<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('calls', 'destination_type')) {
                $table->string('destination_type', 32)->default('workspace_user')->after('caller_name');
            }

            if (Schema::hasColumn('calls', 'caller_phone_number')) {
                $table->dropColumn('caller_phone_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('calls', 'caller_phone_number')) {
                $table->string('caller_phone_number', 16)->nullable()->after('caller_name');
            }

            if (Schema::hasColumn('calls', 'destination_type')) {
                $table->dropColumn('destination_type');
            }
        });
    }
};
