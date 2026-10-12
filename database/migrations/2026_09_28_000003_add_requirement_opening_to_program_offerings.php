<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shortlist and disbursement requirements stay hidden from applicants
     * until staff open them: shortlist ones from the Shortlist tab once the
     * shortlist fills the slots, disbursement ones from the Grantees page
     * once every slot is awarded.
     */
    public function up(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dateTime('shortlist_requirements_opened_at')->nullable()->after('interview_instructions');
            $table->dateTime('award_requirements_opened_at')->nullable()->after('shortlist_requirements_opened_at');
        });

        // Offerings already past these stages keep showing their requirements.
        DB::table('program_offerings')
            ->whereExists(fn ($query) => $query->from('applications')
                ->whereColumn('applications.program_offering_id', 'program_offerings.id')
                ->where('applications.kind', 'new')
                ->whereIn('applications.status', ['shortlisted', 'awarded']))
            ->update(['shortlist_requirements_opened_at' => now()]);

        DB::table('program_offerings')
            ->whereExists(fn ($query) => $query->from('awards')
                ->whereColumn('awards.program_offering_id', 'program_offerings.id'))
            ->update(['award_requirements_opened_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn(['shortlist_requirements_opened_at', 'award_requirements_opened_at']);
        });
    }
};
