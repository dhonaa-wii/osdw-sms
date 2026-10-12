<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A renewal offering now names the offering it continues
     * (`renews_offering_id`) instead of guessing it from a renewal cycle, and
     * takes only renewing scholars. Renewing is decided on renewal-stage
     * additional requirements that staff verify, so the typed grades, the
     * copy-of-grades columns, retention rules and renewal slots all go.
     */
    public function up(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->foreignId('renews_offering_id')->nullable()->after('scholarship_program_id')
                ->constrained('program_offerings')->restrictOnDelete();
        });

        // Offerings that already hold renewals continue the offering those
        // renewals came from.
        DB::table('applications')
            ->join('awards', 'awards.id', '=', 'applications.renewed_from_award_id')
            ->where('applications.kind', 'renewal')
            ->select('applications.program_offering_id', 'awards.program_offering_id as source_id')
            ->distinct()
            ->get()
            ->each(fn ($row) => DB::table('program_offerings')
                ->where('id', $row->program_offering_id)
                ->update(['renews_offering_id' => $row->source_id, 'total_slots' => 0]));

        // Renewals are under review from the moment they are opened.
        DB::table('applications')
            ->where('kind', 'renewal')
            ->where('status', 'draft')
            ->update(['status' => 'screening']);

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn(['renewal_cycle', 'renewal_slots']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['units_enrolled', 'failing_grades', 'grades_path', 'grades_original_name', 'grades_mime_type']);
        });

        Schema::dropIfExists('offering_retention_rules');
    }

    public function down(): void
    {
        Schema::create('offering_retention_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_offering_id')->constrained()->cascadeOnDelete();
            $table->string('field', 20);
            $table->string('operator', 10);
            $table->string('value', 255);
            $table->string('message', 255);
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('units_enrolled')->nullable()->after('monthly_income');
            $table->unsignedTinyInteger('failing_grades')->nullable()->after('units_enrolled');
            $table->string('grades_path')->nullable()->after('failing_grades');
            $table->string('grades_original_name')->nullable()->after('grades_path');
            $table->string('grades_mime_type', 100)->nullable()->after('grades_original_name');
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->string('renewal_cycle', 20)->default('none')->after('amount');
            $table->unsignedSmallInteger('renewal_slots')->default(0)->after('renewal_cycle');
            $table->dropConstrainedForeignId('renews_offering_id');
        });
    }
};
