<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_reactions')
            ->select('message_id', 'user_id', DB::raw('MAX(id) as kept_id'))
            ->groupBy('message_id', 'user_id')
            ->orderBy('message_id')
            ->chunk(100, function ($reactions): void {
                foreach ($reactions as $reaction) {
                    DB::table('message_reactions')
                        ->where('message_id', $reaction->message_id)
                        ->where('user_id', $reaction->user_id)
                        ->where('id', '!=', $reaction->kept_id)
                        ->delete();
                }
            });

        Schema::table('message_reactions', function (Blueprint $table): void {
            $table->dropUnique('message_reactions_message_id_user_id_emoji_unique');
            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('message_reactions', function (Blueprint $table): void {
            $table->dropUnique('message_reactions_message_id_user_id_unique');
            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }
};
