<?php

namespace Tests\Feature\Staff;

use App\Enums\DegreeLevel;
use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Mail\AwardNoticeMail;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AwardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AwardProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function programOffering(array $overrides = []): ProgramOffering
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

        return ProgramOffering::create(array_merge([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 1,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => 5000,
        ], $overrides));
    }

    /**
     * A shortlisted application. $order lets a test control submission
     * order (lower = submitted earlier), which is how the award queue
     * without a rank is sorted.
     */
    private function shortlisted(ProgramOffering $offering, int $order = 1, ?string $studentId = null): Application
    {
        $college = College::firstOrCreate(['code' => 'CICS'], ['name' => 'College of ICS']);
        $program = Program::firstOrCreate(
            ['code' => 'BSIT', 'college_id' => $college->id],
            ['name' => 'BS Information Technology', 'degree_level' => DegreeLevel::Undergraduate]
        );
        $applicant = Applicant::create([
            'student_id' => $studentId ?? 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);

        return $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'shortlisted',
            'submitted_at' => now()->subDays(10 - $order),
        ]);
    }

    public function test_staff_can_view_the_awards_picker(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();

        $this->actingAs($staff)
            ->get(route('staff.awards.index'))
            ->assertOk()
            ->assertSee($offering->scholarshipProgram->name);
    }

    public function test_staff_can_award_a_shortlisted_candidate_from_the_applications_workspace(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering, 1);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('startAward', $application->id)
            ->call('confirmAward');

        $this->assertDatabaseHas('awards', ['application_id' => $application->id, 'status' => 'reserved']);
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'approved']);
        Mail::assertSent(AwardNoticeMail::class);
    }

    public function test_awarding_beyond_slot_capacity_is_blocked(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering(['total_slots' => 1]);
        $first = $this->shortlisted($offering, 1);
        $second = $this->shortlisted($offering, 2);

        app(AwardService::class)->create($first, $staff);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('startAward', $second->id)
            ->call('confirmAward')
            ->assertHasErrors(['slots']);

        $this->assertDatabaseMissing('awards', ['application_id' => $second->id]);
    }

    public function test_staff_can_award_any_shortlisted_candidate_without_justification(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering(['total_slots' => 5]);
        $this->shortlisted($offering, 1);
        $second = $this->shortlisted($offering, 2);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->call('startAward', $second->id)
            ->call('confirmAward')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('awards', ['application_id' => $second->id]);
    }

    public function test_applicant_can_accept_award_and_it_activates_immediately(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering, 1);
        $award = app(AwardService::class)->create($application, $staff);

        $this->withSession(['tracking_applicant_id' => $application->applicant_id]);

        Livewire::test('public::track-dashboard')
            ->call('acceptAward', $award->id);

        $award->refresh();
        $this->assertSame('accepted', $award->acceptance_status->value);
        $this->assertSame('active', $award->status->value);
        $this->assertSame('accepted', $application->fresh()->status->value);
    }

    public function test_applicant_can_decline_award_and_slot_frees_up(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering(['total_slots' => 1]);
        $application = $this->shortlisted($offering, 1);
        $award = app(AwardService::class)->create($application, $staff);

        $this->withSession(['tracking_applicant_id' => $application->applicant_id]);
        Livewire::test('public::track-dashboard')->call('declineAward', $award->id);

        $this->assertSame('declined', $award->fresh()->acceptance_status->value);
        $this->assertSame('withdrawn', $application->fresh()->status->value);
        $this->assertSame(1, app(AwardService::class)->remainingSlots($offering->fresh()));
    }

    public function test_conflicting_active_award_is_flagged_but_not_blocked(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offeringA = $this->programOffering();
        $offeringB = $this->programOffering(['total_slots' => 5]);
        $applicationA = $this->shortlisted($offeringA, 1, 'SID-SHARED');
        app(AwardService::class)->create($applicationA, $staff);

        $college = College::first();
        $applicationB = $applicationA->applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('b', 64),
            'program_offering_id' => $offeringB->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'shortlisted',
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offeringB])
            ->assertSee('Holds another active award');
    }

    public function test_staff_can_forfeit_an_award_with_reason(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->shortlisted($offering, 1);
        $award = app(AwardService::class)->create($application, $staff);
        app(AwardService::class)->accept($award);

        Livewire::actingAs($staff)
            ->test('staff::awards.workspace', ['offering' => $offering])
            ->call('startForfeit', $award->id)
            ->call('confirmForfeit')
            ->assertHasErrors(['forfeitReason']);

        Livewire::actingAs($staff)
            ->test('staff::awards.workspace', ['offering' => $offering])
            ->call('startForfeit', $award->id)
            ->set('forfeitReason', 'Withdrew from the program.')
            ->call('confirmForfeit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('awards', ['id' => $award->id, 'status' => 'terminated']);
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'forfeited']);
    }
}
