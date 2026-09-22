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
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['document_score', 'composite_score', 'rank_in_college']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->decimal('document_score', 6, 2)->nullable();
            $table->decimal('composite_score', 6, 2)->nullable();
            $table->integer('rank_in_college')->nullable();
        });
    }
};
