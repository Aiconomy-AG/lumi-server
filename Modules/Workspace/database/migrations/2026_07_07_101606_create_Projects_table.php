<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->dateTime("deadline");
            $table->string("description");
            $table->enum("status",["complete","to_do","in_progress","blocked"]);
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table){
            $table->foreignId("project_id")->constrained("projects")->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
