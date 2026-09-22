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
            $table->decimal('gwa', 5, 2)->nullable()->change();
        });

        Schema::table('scholar_terms', function (Blueprint $table) {
            $table->decimal('gwa', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->decimal('gwa', 4, 2)->nullable()->change();
        });

        Schema::table('scholar_terms', function (Blueprint $table) {
            $table->decimal('gwa', 4, 2)->nullable()->change();
        });
    }
};
