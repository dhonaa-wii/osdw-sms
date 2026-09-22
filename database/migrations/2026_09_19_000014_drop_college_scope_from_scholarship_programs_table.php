<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * College scope is a per-term decision, so it lives on the offering only.
     */
    public function up(): void
    {
        Schema::table('scholarship_programs', function (Blueprint $table) {
            $table->dropColumn('college_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scholarship_programs', function (Blueprint $table) {
            $table->string('college_scope', 20)->default('all')->after('category');
        });
    }
};
