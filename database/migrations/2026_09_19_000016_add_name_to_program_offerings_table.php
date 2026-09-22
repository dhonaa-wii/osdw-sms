<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin's own label for this run of the program — "Batch 1",
     * "First semester intake". Nullable so offerings opened before naming
     * existed still resolve, falling back to their term.
     */
    public function up(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->string('name', 150)->nullable()->after('academic_term_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
