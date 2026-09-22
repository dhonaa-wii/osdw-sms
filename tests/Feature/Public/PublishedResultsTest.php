<?php

namespace Tests\Feature\Public;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Award;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PublishedResultsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAward(bool $resultsPublished, bool $consentPublish, string $lastName = 'Dela Cruz'): Award
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri '.Str::random(4)]);
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => 5000, 'results_published' => $resultsPublished,
        ]);
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => $lastName, 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);
        $application = $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'accepted', 'submitted_at' => now(), 'consent_publish' => $consentPublish,
        ]);
        $staff = User::factory()->create();

        return Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
            'acceptance_status' => 'accepted', 'accepted_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_published_award_with_consent_appears(): void
    {
        $this->makeAward(resultsPublished: true, consentPublish: true, lastName: 'Santos');

        Livewire::test('public::results')->assertSee('Santos');
    }

    public function test_award_from_unpublished_offering_is_hidden(): void
    {
        $this->makeAward(resultsPublished: false, consentPublish: true, lastName: 'Reyes');

        Livewire::test('public::results')->assertDontSee('Reyes');
    }

    public function test_award_without_publication_consent_is_hidden(): void
    {
        $this->makeAward(resultsPublished: true, consentPublish: false, lastName: 'Cruz');

        Livewire::test('public::results')->assertDontSee('Cruz');
    }
}
