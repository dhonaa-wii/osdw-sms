<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renewals ride on the applications table: a renewal is an application
     * of kind `renewal` in the next offering, generated from an award in the
     * previous one. The offering says whether its awardees renew (and how
     * often) and how many slots it reserves for renewing scholars.
     */
    public function up(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->string('renewal_cycle', 20)->default('none')->after('amount');
            $table->unsignedSmallInteger('renewal_slots')->default(0)->after('renewal_cycle');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('kind', 20)->default('new')->after('program_offering_id');
            $table->foreignId('renewed_from_award_id')->nullable()->after('kind')->constrained('awards')->nullOnDelete();
            $table->unsignedSmallInteger('units_enrolled')->nullable()->after('monthly_income');
            $table->unsignedTinyInteger('failing_grades')->nullable()->after('units_enrolled');
            $table->string('grades_path')->nullable()->after('failing_grades');
            $table->string('grades_original_name')->nullable()->after('grades_path');
            $table->string('grades_mime_type', 100)->nullable()->after('grades_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewed_from_award_id');
            $table->dropColumn(['kind', 'units_enrolled', 'failing_grades', 'grades_path', 'grades_original_name', 'grades_mime_type']);
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn(['renewal_cycle', 'renewal_slots']);
        });
    }
};
