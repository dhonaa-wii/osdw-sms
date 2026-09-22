<?php

namespace Tests\Feature\Public;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Mail\ApplicationReferenceMail;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function applicantWithApplication(): array
    {
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open',
        ]);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => '2023-00123', 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'birthdate' => '2003-05-14', 'email' => 'juan@example.com',
        ]);
        $application = $applicant->applications()->create([
            'reference_no' => 'SCH-2026-AAAAAA', 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        return compact('applicant', 'application', 'offering');
    }

    public function test_correct_student_id_and_reference_grants_tracking_access(): void
    {
        ['applicant' => $applicant] = $this->applicantWithApplication();

        Livewire::test('public::track-lookup')
            ->set('student_id', '2023-00123')
            ->set('reference_no', 'SCH-2026-AAAAAA')
            ->call('submit')
            ->assertRedirect(route('track.dashboard'));

        $this->assertSame($applicant->id, session('tracking_applicant_id'));
    }

    public function test_wrong_reference_number_is_rejected(): void
    {
        $this->applicantWithApplication();

        Livewire::test('public::track-lookup')
            ->set('student_id', '2023-00123')
            ->set('reference_no', 'SCH-2026-WRONG1')
            ->call('submit')
            ->assertHasErrors(['reference_no']);

        $this->assertNull(session('tracking_applicant_id'));
    }

    public function test_dashboard_requires_a_verified_session(): void
    {
        ['application' => $application] = $this->applicantWithApplication();

        $this->get(route('track.dashboard'))->assertForbidden();
    }

    public function test_dashboard_shows_application_and_status_timeline(): void
    {
        ['applicant' => $applicant, 'application' => $application] = $this->applicantWithApplication();
        $application->statusLogs()->create(['from_status' => null, 'to_status' => 'submitted']);

        $this->withSession(['tracking_applicant_id' => $applicant->id])
            ->get(route('track.dashboard'))
            ->assertOk()
            ->assertSee($application->reference_no)
            ->assertSee('Submitted');
    }

    public function test_applicant_can_withdraw_an_application(): void
    {
        ['applicant' => $applicant, 'application' => $application] = $this->applicantWithApplication();

        $this->withSession(['tracking_applicant_id' => $applicant->id]);

        Livewire::test('public::track-dashboard')
            ->call('withdraw', $application->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'withdrawn']);
        $this->assertDatabaseHas('application_status_logs', ['application_id' => $application->id, 'to_status' => 'withdrawn']);
    }

    public function test_recover_reference_finds_application_by_identity(): void
    {
        $this->applicantWithApplication();

        Livewire::test('public::track-recover')
            ->set('student_id', '2023-00123')
            ->set('last_name', 'Dela Cruz')
            ->set('birthdate', '2003-05-14')
            ->call('submit')
            ->assertSee('SCH-2026-AAAAAA');
    }

    public function test_recover_reference_does_not_leak_data_for_wrong_birthdate(): void
    {
        $this->applicantWithApplication();

        Livewire::test('public::track-recover')
            ->set('student_id', '2023-00123')
            ->set('last_name', 'Dela Cruz')
            ->set('birthdate', '1990-01-01')
            ->call('submit')
            ->assertDontSee('SCH-2026-AAAAAA');
    }

    public function test_confirmation_email_is_sent_and_logged_on_submission(): void
    {
        Mail::fake();

        $offering = $this->applicantWithApplication()['offering'];
        $college = College::create(['code' => 'COE', 'name' => 'College of Engineering']);

        Livewire::test('public::apply', ['offering' => $offering])
            ->set('student_id', '2024-00999')
            ->set('last_name', 'Santos')
            ->set('first_name', 'Maria')
            ->set('sex', 'female')
            ->set('email', 'maria@example.com')
            ->set('college_id', (string) $college->id)
            ->set('year_level', '1')
            ->set('gwa', '92.00')
            ->call('submit')
            ->assertHasNoErrors();

        Mail::assertSent(ApplicationReferenceMail::class, fn ($mail) => $mail->hasTo('maria@example.com'));

        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'maria@example.com',
            'type' => 'reference',
            'status' => 'sent',
        ]);
    }
}
