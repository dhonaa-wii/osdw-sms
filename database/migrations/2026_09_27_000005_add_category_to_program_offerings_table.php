<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Type of scholarship is a per-term decision, so it moves onto the
     * offering — same treatment as college scope and allocation mode.
     * Existing offerings are backfilled from their program's category
     * before the column on scholarship_programs is dropped and this one
     * becomes required.
     */
    public function up(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('name');
        });

        DB::table('program_offerings')
            ->whereNull('category')
            ->update([
                'category' => DB::raw(
                    '(select category from scholarship_programs where scholarship_programs.id = program_offerings.scholarship_program_id)'
                ),
            ]);

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->string('category', 20)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
