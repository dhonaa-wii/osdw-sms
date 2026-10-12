<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A renewal offering can also take new applicants, filling the slots of
     * scholars in their last term. Renewing scholars now type their year
     * level each term, so a renewal starts without one.
     */
    public function up(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->boolean('accepts_new_applicants')->default(false)->after('renews_offering_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->tinyInteger('year_level')->nullable()->change();
        });

        // Renewals still under review ask the scholar for this term's year level.
        DB::table('applications')
            ->where('kind', 'renewal')
            ->where('status', 'screening')
            ->update(['year_level' => null]);
    }

    public function down(): void
    {
        DB::table('applications')
            ->whereNull('year_level')
            ->update(['year_level' => 1]);

        Schema::table('applications', function (Blueprint $table) {
            $table->tinyInteger('year_level')->nullable(false)->change();
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn('accepts_new_applicants');
        });
    }
};
