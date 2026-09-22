<?php

namespace Tests\Feature\Public;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\Campus;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProgramAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function openOffering(): ProgramOffering
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);

        return ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => 5000,
        ]);
    }

    public function test_published_announcement_for_the_offering_is_visible(): void
    {
        $staff = User::factory()->create();
        $offering = $this->openOffering();
        Announcement::create([
            'program_offering_id' => $offering->id, 'title' => 'Deadline extended',
            'body' => 'The application window is now open until next month.',
            'is_published' => true, 'published_at' => now(), 'created_by' => $staff->id,
        ]);

        $this->get(route('programs.show', $offering))->assertSee('Deadline extended');
    }

    public function test_unpublished_announcement_is_hidden(): void
    {
        $staff = User::factory()->create();
        $offering = $this->openOffering();
        Announcement::create([
            'program_offering_id' => $offering->id, 'title' => 'Internal draft note',
            'body' => 'Not ready yet.', 'is_published' => false, 'created_by' => $staff->id,
        ]);

        $this->get(route('programs.show', $offering))->assertDontSee('Internal draft note');
    }
}
