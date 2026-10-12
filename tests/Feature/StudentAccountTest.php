<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\NotificationKind;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Mail\OtpMail;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Award;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\OfferingRequirementService;
use App\Services\StudentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StudentAccountTest extends TestCase
{
    use RefreshDatabase;

    private function offering(array $overrides = [], string $name = 'DOST-SEI Fellowship'): ProgramOffering
    {
        $provider = Provider::create(['name' => 'Provider '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $program = ScholarshipProgram::create(['name' => $name, 'provider_id' => $provider->id]);
        $term = AcademicTerm::firstOrCreate(['school_year' => '2026-2027', 'semester' => Semester::First], ['start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true]);
        $campus = Campus::firstOrCreate(['name' => 'Aparri']);

        return ProgramOffering::create(array_merge([
            'scholarship_program_id' => $program->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'total_slots' => 5, 'amount' => 10000,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::NonAcademic,
        ], $overrides));
    }

    private function student(string $studentId = '2023-00001', string $email = 'juan@example.test'): User
    {
        $user = User::create([
            'name' => 'Juan Cruz', 'email' => $email, 'password' => 'password',
            'role' => UserRole::Student, 'is_active' => true, 'email_verified_at' => now(),
        ]);

        Applicant::create([
            'user_id' => $user->id, 'student_id' => $studentId, 'last_name' => 'Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => $email,
        ]);

        return $user->fresh();
    }

    private function apply(User $user, ProgramOffering $offering, array $attributes = []): Application
    {
        return Application::create(array_merge([
            'applicant_id' => $user->applicant->id, 'program_offering_id' => $offering->id,
            'college_id' => College::firstOrCreate(['code' => 'CICS'], ['name' => 'Computing'])->id,
            'year_level' => 2, 'consent_publish' => true, 'status' => ApplicationStatus::Screening, 'submitted_at' => now(),
        ], $attributes));
    }

    public function test_registration_needs_the_emailed_code_then_a_password(): void
    {
        Mail::fake();

        $component = Livewire::test('auth::register')
            ->set('email', 'New.Student@Example.test')
            ->call('sendCode')
            ->assertSet('step', 'code');

        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('new.student@example.test');
        });

        $component->set('code', $code === '000000' ? '111111' : '000000')
            ->call('verifyCode')
            ->assertHasErrors('code')
            // Pasted from the email with a leading line break and a space in the middle.
            ->set('code', "\n ".substr($code, 0, 3).' '.substr($code, 3))
            ->call('verifyCode')
            ->assertSet('step', 'password')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('createAccount')
            ->assertRedirect(route('student.profile.complete'));

        $user = User::where('email', 'new.student@example.test')->firstOrFail();
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_code_from_an_earlier_email_still_works_after_a_resend(): void
    {
        Mail::fake();

        $component = Livewire::test('auth::register')->set('email', 'maria@example.test')->call('sendCode');
        $this->travel(61)->seconds();
        $component->call('resendCode')->assertHasNoErrors();

        $codes = [];
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$codes) {
            $codes[] = $mail->code;

            return str_starts_with($mail->subject ?? $mail->purpose->subject($mail->code), $mail->code);
        });
        $this->assertCount(2, $codes);

        $component->set('code', $codes[0])->call('verifyCode')->assertSet('step', 'password');
    }

    public function test_profile_setup_rejects_a_student_id_that_is_already_registered(): void
    {
        $this->student('2023-00001');
        $newcomer = User::create(['name' => 'x', 'email' => 'maria@example.test', 'password' => 'password', 'role' => UserRole::Student, 'is_active' => true]);

        Livewire::actingAs($newcomer)->test('student::complete-profile')
            ->set('student_id', '2023-00001')
            ->set('last_name', 'Santos')->set('first_name', 'Maria')->set('sex', 'female')
            ->call('save')
            ->assertHasErrors('student_id');

        Livewire::actingAs($newcomer)->test('student::complete-profile')
            ->set('student_id', '2023-00002')
            ->set('last_name', 'Santos')->set('first_name', 'Maria')->set('sex', 'female')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2023-00002', $newcomer->fresh()->applicant->student_id);
    }

    public function test_student_without_a_profile_is_sent_to_set_one_up(): void
    {
        $user = User::create(['name' => 'x', 'email' => 'x@example.test', 'password' => 'password', 'role' => UserRole::Student, 'is_active' => true]);

        $this->actingAs($user)->get(route('student.applications.index'))
            ->assertRedirect(route('student.profile.complete'));
    }

    public function test_guest_clicking_apply_is_sent_to_log_in(): void
    {
        $offering = $this->offering();

        $this->get(route('programs.apply', $offering))->assertRedirect(route('login'));
    }

    public function test_public_browse_lists_only_open_offerings(): void
    {
        $this->offering(name: 'Open Grant');
        $this->offering(['opens_at' => now()->subMonths(2), 'closes_at' => now()->subMonth()], 'Closed Grant');
        $closed = ProgramOffering::latest('id')->first();

        $this->get('/programs')->assertOk()->assertSee('Open Grant')->assertDontSee('Closed Grant');
        $this->get(route('programs.show', $closed))->assertNotFound();
    }

    public function test_student_applies_from_their_profile(): void
    {
        $offering = $this->offering();
        $user = $this->student();
        $college = College::firstOrCreate(['code' => 'CICS'], ['name' => 'Computing']);

        Livewire::actingAs($user)->test('student::apply', ['offering' => $offering])
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->set('monthly_income', '15000')
            ->call('goToReview')
            ->assertSet('reviewing', true)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applications', ['applicant_id' => $user->applicant->id, 'program_offering_id' => $offering->id]);
    }

    public function test_academic_offering_refuses_an_application_below_the_required_gwa(): void
    {
        $offering = $this->offering(['category' => ScholarshipCategory::Academic]);
        $offering->eligibilityRules()->create([
            'field' => 'gwa', 'operator' => 'gte', 'value' => '85',
            'message' => 'You need a GWA of at least 85 to apply for this scholarship.',
        ]);
        $user = $this->student();
        $college = College::firstOrCreate(['code' => 'CICS'], ['name' => 'Computing']);

        $component = Livewire::actingAs($user)->test('student::apply', ['offering' => $offering])
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->set('monthly_income', '15000')
            ->set('gwa', '84.50')
            ->assertHasErrors('gwa')
            ->call('goToReview')
            ->assertHasErrors('gwa')
            ->assertSet('reviewing', false)
            ->call('submit')
            ->assertHasErrors('gwa');

        $this->assertDatabaseMissing('applications', ['applicant_id' => $user->applicant->id]);

        $component->set('gwa', '85')
            ->assertHasNoErrors('gwa')
            ->call('goToReview')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applications', ['applicant_id' => $user->applicant->id, 'program_offering_id' => $offering->id]);
    }

    public function test_offering_refuses_a_family_income_above_its_cap_without_showing_it(): void
    {
        $offering = $this->offering();
        app(EligibilityService::class)->saveIncomeCap($offering, '20000');
        $user = $this->student();
        $college = College::firstOrCreate(['code' => 'CICS'], ['name' => 'Computing']);

        $this->get(route('programs.show', $offering))->assertOk()->assertDontSee('20000')->assertDontSee('Family monthly income');

        $component = Livewire::actingAs($user)->test('student::apply', ['offering' => $offering])
            ->set('college_id', (string) $college->id)
            ->set('year_level', '2')
            ->set('monthly_income', '20000.01')
            ->assertHasErrors('monthly_income')
            ->assertDontSee('20000')
            ->call('goToReview')
            ->assertSet('reviewing', false)
            ->call('submit')
            ->assertHasErrors('monthly_income');

        $this->assertDatabaseMissing('applications', ['applicant_id' => $user->applicant->id]);

        $component->set('monthly_income', '20000')
            ->assertHasNoErrors('monthly_income')
            ->call('goToReview')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applications', ['applicant_id' => $user->applicant->id, 'monthly_income' => 20000]);
    }

    public function test_a_blank_income_cap_removes_it(): void
    {
        $offering = $this->offering();
        $eligibility = app(EligibilityService::class);

        $eligibility->saveIncomeCap($offering, '20000');
        $this->assertSame('20000', $eligibility->incomeCap($offering));

        $eligibility->saveIncomeCap($offering, '');
        $this->assertNull($eligibility->incomeCap($offering));
    }

    public function test_student_sees_only_their_own_applications(): void
    {
        $offering = $this->offering();
        $juan = $this->student('2023-00001', 'juan@example.test');
        $maria = $this->student('2023-00002', 'maria@example.test');
        $mariasApplication = $this->apply($maria, $offering);
        $this->apply($juan, $offering);

        $this->actingAs($juan)->get(route('student.applications.index'))->assertOk()->assertSee('DOST-SEI Fellowship');
        $this->actingAs($juan)->get(route('student.applications.show', $mariasApplication))->assertNotFound();
    }

    public function test_status_changes_reach_the_students_bell_but_their_own_submission_does_not(): void
    {
        $offering = $this->offering();
        $user = $this->student();
        $application = $this->apply($user, $offering);

        ApplicationStatusLog::create(['application_id' => $application->id, 'from_status' => ApplicationStatus::Submitted, 'to_status' => ApplicationStatus::Screening]);
        $this->assertSame(0, $user->notifications()->count());

        ApplicationStatusLog::create(['application_id' => $application->id, 'from_status' => ApplicationStatus::Screening, 'to_status' => ApplicationStatus::Shortlisted]);
        $notification = $user->notifications()->first();
        $this->assertSame('You are on the shortlist', $notification->data['title']);
        $this->assertSame(route('student.applications.show', $application, absolute: false), $notification->data['url']);
    }

    public function test_scheduling_the_interview_notifies_only_shortlisted_applicants(): void
    {
        $offering = $this->offering();
        $shortlisted = $this->student('2023-00001', 'a@example.test');
        $screening = $this->student('2023-00002', 'b@example.test');
        $this->apply($shortlisted, $offering, ['status' => ApplicationStatus::Shortlisted]);
        $this->apply($screening, $offering);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        app(OfferingRequirementService::class)->saveInterview($offering, [
            'has_interview' => true, 'interview_at' => now()->addWeek()->format('Y-m-d H:i'), 'interview_venue' => 'OSDW Office', 'interview_instructions' => null,
        ]);

        $this->assertSame(NotificationKind::InterviewScheduled->value, $shortlisted->notifications()->first()?->data['kind']);
        $this->assertSame(0, $screening->notifications()->count());
        $this->assertSame(NotificationKind::InterviewScheduled->value, $staff->notifications()->first()?->data['kind']);
    }

    public function test_announcements_reach_applicants_or_everyone(): void
    {
        $offering = $this->offering();
        $applicant = $this->student('2023-00001', 'a@example.test');
        $other = $this->student('2023-00002', 'b@example.test');
        $this->apply($applicant, $offering);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $service = app(StudentNotificationService::class);

        $service->announcementPublished(Announcement::create(['program_offering_id' => $offering->id, 'title' => 'Shortlist soon', 'body' => 'x', 'is_published' => true, 'published_at' => now(), 'created_by' => $staff->id]));
        $this->assertSame(1, $applicant->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());

        $service->announcementPublished(Announcement::create(['title' => 'Office closed', 'body' => 'x', 'is_published' => true, 'published_at' => now(), 'created_by' => $staff->id]));
        $this->assertSame(2, $applicant->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_every_student_hears_once_when_registration_opens_and_closes(): void
    {
        $offering = $this->offering(['max_applications' => 1]);
        $juan = $this->student('2023-00001', 'a@example.test');
        $maria = $this->student('2023-00002', 'b@example.test');
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $service = app(StudentNotificationService::class);

        $service->syncRegistrationNotices();
        $service->syncRegistrationNotices();
        $this->assertSame(1, $maria->notifications()->where('data->kind', NotificationKind::RegistrationOpened->value)->count());

        // The cap is reached: registration closes before its deadline.
        $this->apply($juan, $offering);
        $service->syncRegistrationNotices();
        $this->assertSame(1, $maria->notifications()->where('data->kind', NotificationKind::RegistrationClosed->value)->count());
        $this->assertNotNull($offering->fresh()->registration_closed_notified_at);

        // Staff hear once when it opens and once when it closes.
        $this->assertSame(1, $staff->notifications()->where('data->kind', NotificationKind::RegistrationOpened->value)->count());
        $this->assertSame(1, $staff->notifications()->where('data->kind', NotificationKind::RegistrationClosed->value)->count());
    }

    public function test_results_count_awardees_who_did_not_consent_without_naming_them(): void
    {
        $offering = $this->offering(['results_published' => true, 'results_published_at' => now()]);
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        foreach ([['2023-00001', 'a@example.test', true], ['2023-00002', 'b@example.test', false]] as [$id, $email, $consent]) {
            $user = $this->student($id, $email);
            $user->applicant->update(['last_name' => $consent ? 'Listed' : 'Hidden']);
            $application = $this->apply($user, $offering, ['consent_publish' => $consent, 'status' => ApplicationStatus::Awarded]);
            Award::create(['application_id' => $application->id, 'applicant_id' => $user->applicant->id, 'program_offering_id' => $offering->id, 'academic_term_id' => $offering->academic_term_id, 'amount' => 10000, 'awarded_by' => $staff->id, 'awarded_at' => now()]);
        }

        $this->get(route('programs.results', $offering))
            ->assertOk()
            ->assertSee('Listed')
            ->assertDontSee('Hidden')
            ->assertSee('1 more awardee chose not to have their name published.');
    }

    public function test_student_and_sign_in_pages_render(): void
    {
        $offering = $this->offering();
        $user = $this->student();
        $application = $this->apply($user, $offering);
        ApplicationStatusLog::create(['application_id' => $application->id, 'from_status' => ApplicationStatus::Screening, 'to_status' => ApplicationStatus::Shortlisted]);

        foreach (['/', '/login', '/register', '/forgot-password', '/programs', route('programs.show', $offering)] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($user);
        foreach ([
            route('student.applications.index'),
            route('student.applications.index', ['status' => 'awarded']),
            route('student.applications.show', $application),
            route('student.notifications'),
            route('student.settings'),
            route('programs.show', $offering),
            '/',
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('You are on the shortlist', false);
        }

        $this->get(route('programs.apply', $offering))->assertRedirect(route('student.applications.show', $application));
        $this->get(route('dashboard'))->assertForbidden();
        $this->get('/login')->assertRedirect(route('student.applications.index'));
    }

    public function test_forgotten_password_is_reset_with_an_emailed_code(): void
    {
        Mail::fake();
        $user = $this->student();

        $component = Livewire::test('auth::forgot-password')->set('email', 'juan@example.test')->call('sendCode')->assertSet('step', 'code');

        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $component->set('code', $code)->call('verifyCode')
            ->set('password', 'brandnew1')->set('password_confirmation', 'brandnew1')
            ->call('resetPassword');

        $this->assertTrue(Hash::check('brandnew1', $user->fresh()->password));
    }
}
