<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_events')) {
            if (! Schema::hasColumn('notification_events', 'message_id')) {
                Schema::table('notification_events', function (Blueprint $table) {
                    $table->foreignId('message_id')->nullable()->after('conversation_id')->constrained('messages')->nullOnDelete();
                });
            }

            if (Schema::hasColumn('notification_events', 'message_id') && ! $this->hasMessagesForeignKey()) {
                Schema::table('notification_events', function (Blueprint $table) {
                    $table->foreign('message_id')->references('id')->on('messages')->nullOnDelete();
                });
            }

            return;
        }

        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('source');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['source', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }

    private function hasMessagesForeignKey(): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'notification_events')
            ->where('COLUMN_NAME', 'message_id')
            ->where('REFERENCED_TABLE_NAME', 'messages')
            ->exists();
    }
};
