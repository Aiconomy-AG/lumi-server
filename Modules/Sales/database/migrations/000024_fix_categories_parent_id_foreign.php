<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasParentForeignKey()) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->change();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->hasParentForeignKey()) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign('categories_parent_id_foreign');
        });
    }

    private function hasParentForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('categories'))
            ->contains(fn ($fk) => $fk['name'] === 'categories_parent_id_foreign');
    }
};
