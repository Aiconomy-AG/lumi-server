<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void{
        Schema::create('conversations', function(Blueprint $table){
            $table->id();
            $table->enum('type',['direct','group']);
            $table->string("name")->nullable();
            $table->foreignId("created_by")->constrained("users");
            $table->timestamps();
        });
    }

    public function down(): void{
        Schema::dropIfExists('conversations');
    }
};
