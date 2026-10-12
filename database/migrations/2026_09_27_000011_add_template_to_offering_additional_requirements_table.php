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
        Schema::table('offering_additional_requirements', function (Blueprint $table) {
            $table->string('template_path', 255)->nullable()->after('max_size_mb');
            $table->string('template_name', 255)->nullable()->after('template_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offering_additional_requirements', function (Blueprint $table) {
            $table->dropColumn(['template_path', 'template_name']);
        });
    }
};
