<?php

namespace Tests\Feature\Staff;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Award;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function award(AcademicTerm $term, Campus $campus, ?Applicant $applicant = null): Award
    {
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
        ]);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
            'amount' => 5000,
        ]);
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $applicant ??= Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);
        $application = $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'awarded', 'submitted_at' => now(),
        ]);
        $staff = User::factory()->create();

        return Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
        ]);
    }

    private function term(): AcademicTerm
    {
        return AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
    }

    public function test_suc_levelling_report_lists_awards_with_correct_columns(): void
    {
        $this->award($this->term(), Campus::create(['name' => 'Aparri']));

        $service = app(ReportService::class);
        $rows = $service->sucLevelling($service->granteeQuery()->get());

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(1, $row['Sequence No.']);
        $this->assertSame('Dela Cruz', $row['Last Name']);
        $this->assertSame('Juan', $row['First Name']);
        $this->assertSame('Male', $row['Sex']);
        $this->assertSame('Aparri', $row['Campus']);
        $this->assertEquals(5000, $row['Amount']);
    }

    public function test_suc_levelling_report_follows_the_offering_filter(): void
    {
        $term = $this->term();
        $campus = Campus::create(['name' => 'Aparri']);
        $award = $this->award($term, $campus);
        $this->award($term, $campus);

        $service = app(ReportService::class);
        $rows = $service->sucLevelling($service->granteeQuery(offering: $award->programOffering)->get());

        $this->assertCount(1, $rows);
    }

    public function test_suc_levelling_report_follows_the_search(): void
    {
        $term = $this->term();
        $campus = Campus::create(['name' => 'Aparri']);
        $this->award($term, $campus);
        $this->award($term, $campus, Applicant::create([
            'student_id' => 'SID-MARIA', 'last_name' => 'Santos', 'first_name' => 'Maria',
            'sex' => 'female', 'email' => 'maria@example.com',
        ]));

        $service = app(ReportService::class);
        $rows = $service->sucLevelling($service->granteeQuery('Maria Santos')->get());

        $this->assertCount(1, $rows);
        $this->assertSame('Santos', $rows->first()['Last Name']);
    }

    public function test_staff_can_view_reports_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $this->award($this->term(), Campus::create(['name' => 'Aparri']));

        $this->actingAs($staff)
            ->get(route('staff.reports.index'))
            ->assertOk()
            ->assertSee('Scholarship grantees')
            ->assertSee('Dela Cruz');
    }

    public function test_guest_cannot_view_reports_page(): void
    {
        $this->get(route('staff.reports.index'))->assertRedirect(route('login'));
    }

    public function test_suc_levelling_export_downloads_a_spreadsheet_for_the_chosen_offering(): void
    {
        Excel::fake();

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->award($this->term(), Campus::create(['name' => 'Aparri']));
        $offering = $award->programOffering;

        $this->actingAs($staff)
            ->get(route('staff.reports.suc-levelling.export', ['offering' => $offering->id]))
            ->assertOk();

        Excel::assertDownloaded('suc-levelling-'.str('DOST-SEI Fellowship '.$offering->displayName())->slug().'.xlsx');
    }

    public function test_suc_levelling_export_without_filters_downloads_every_grantee(): void
    {
        Excel::fake();

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $this->award($this->term(), Campus::create(['name' => 'Aparri']));

        $this->actingAs($staff)
            ->get(route('staff.reports.suc-levelling.export'))
            ->assertOk();

        Excel::assertDownloaded('suc-levelling-all.xlsx', fn ($export) => $export->collection()->count() === 1);
    }

    public function test_suc_levelling_print_view_lists_the_grantees(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $award = $this->award($this->term(), Campus::create(['name' => 'Aparri']));

        $this->actingAs($staff)
            ->get(route('staff.reports.suc-levelling.print', ['offering' => $award->program_offering_id]))
            ->assertOk()
            ->assertSee('SUC-Levelling report')
            ->assertSee('Dela Cruz');
    }
}
