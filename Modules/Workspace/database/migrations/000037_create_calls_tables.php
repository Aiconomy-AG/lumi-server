<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('caller_name');
            $table->string('caller_phone_number', 16);
            $table->string('media_type')->default('audio');
            $table->string('status')->default('ringing');
            $table->string('room_name')->unique();
            $table->string('answered_client_instance_id')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('end_reason')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
            $table->index(['initiated_by_user_id', 'status']);
        });

        Schema::create('call_participants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('call_id');
            $table->foreign('call_id')->references('id')->on('calls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->string('status')->default('ringing');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['call_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_participants');
        Schema::dropIfExists('calls');
    }
};
