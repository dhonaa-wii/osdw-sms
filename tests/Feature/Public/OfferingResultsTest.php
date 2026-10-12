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

class OfferingResultsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAward(bool $resultsPublished, bool $consentPublish, string $lastName = 'Dela Cruz', ?string $instructions = null): Award
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri '.Str::random(4)]);
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
        ]);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
            'amount' => 5000, 'results_published' => $resultsPublished, 'publish_instructions' => $instructions,
        ]);
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => $lastName, 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);
        $application = $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'awarded', 'submitted_at' => now(), 'consent_publish' => $consentPublish,
        ]);
        $staff = User::factory()->create();

        return Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
        ]);
    }

    public function test_unpublished_offering_results_page_404s(): void
    {
        $award = $this->makeAward(resultsPublished: false, consentPublish: true);

        $this->get(route('programs.results', $award->programOffering))->assertNotFound();
    }

    public function test_published_offering_results_page_shows_consenting_names_and_instructions(): void
    {
        $award = $this->makeAward(resultsPublished: true, consentPublish: true, lastName: 'Santos', instructions: 'Claim your certificate at Room 204.');

        $this->get(route('programs.results', $award->programOffering))
            ->assertOk()
            ->assertSee('Santos')
            ->assertSee('Claim your certificate at Room 204.');
    }

    public function test_published_offering_results_page_hides_names_without_consent(): void
    {
        $award = $this->makeAward(resultsPublished: true, consentPublish: false, lastName: 'Cruz');

        $this->get(route('programs.results', $award->programOffering))
            ->assertOk()
            ->assertDontSee('Cruz');
    }

    public function test_landing_page_lists_published_offerings_with_a_view_link(): void
    {
        $award = $this->makeAward(resultsPublished: true, consentPublish: true);

        Livewire::test('public::landing')
            ->assertSee($award->programOffering->scholarshipProgram->name)
            ->assertSee(route('programs.results', $award->programOffering));
    }
}
