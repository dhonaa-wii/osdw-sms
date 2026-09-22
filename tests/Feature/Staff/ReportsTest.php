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

    private function activeAward(AcademicTerm $term, Campus $campus, ?Applicant $applicant = null): Award
    {
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => 5000,
        ]);
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $applicant ??= Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);
        $application = $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'accepted', 'submitted_at' => now(),
        ]);
        $staff = User::factory()->create();

        return Award::create([
            'application_id' => $application->id, 'applicant_id' => $applicant->id, 'program_offering_id' => $offering->id,
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => now(),
            'acceptance_status' => 'accepted', 'accepted_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_suc_levelling_report_lists_active_awards_with_correct_columns(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $award = $this->activeAward($term, $campus);

        $rows = app(ReportService::class)->sucLevelling($term);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(1, $row['Sequence No.']);
        $this->assertSame('Dela Cruz', $row['Last Name']);
        $this->assertSame('Juan', $row['First Name']);
        $this->assertSame('Male', $row['Sex']);
        $this->assertSame('Aparri', $row['Campus']);
        $this->assertEquals(5000, $row['Amount']);
    }

    public function test_suc_levelling_report_excludes_awards_from_other_terms(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $otherTerm = AcademicTerm::create([
            'school_year' => '2025-2026', 'semester' => Semester::Second,
            'start_date' => '2026-01-12', 'end_date' => '2026-05-29',
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $this->activeAward($otherTerm, $campus);

        $rows = app(ReportService::class)->sucLevelling($term);

        $this->assertCount(0, $rows);
    }

    public function test_scholars_per_college_aggregates_counts_and_amounts(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $this->activeAward($term, $campus);
        $this->activeAward($term, $campus);

        $result = app(ReportService::class)->scholarsPerCollege($term);

        $this->assertSame(2, $result->sum('scholars'));
        $this->assertEquals(10000, $result->sum('amount'));
    }

    public function test_multiple_award_report_finds_applicants_with_more_than_one_active_grant(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $applicant = Applicant::create([
            'student_id' => 'SID-SHARED', 'last_name' => 'Santos', 'first_name' => 'Maria',
            'sex' => 'female', 'email' => 'maria@example.com',
        ]);
        $this->activeAward($term, $campus, $applicant);
        $this->activeAward($term, $campus, $applicant);
        $this->activeAward($term, $campus);

        $result = app(ReportService::class)->multipleAwardReport($term);

        $this->assertCount(1, $result);
        $this->assertSame('SID-SHARED', $result->first()['student_id']);
        $this->assertSame(2, $result->first()['awards']);
    }

    public function test_staff_can_view_reports_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        Campus::create(['name' => 'Aparri']);

        $this->actingAs($staff)
            ->get(route('staff.reports.index'))
            ->assertOk()
            ->assertSee('SUC-Levelling summary');
    }

    public function test_guest_cannot_view_reports_page(): void
    {
        $this->get(route('staff.reports.index'))->assertRedirect(route('login'));
    }

    public function test_suc_levelling_export_downloads_a_spreadsheet(): void
    {
        Excel::fake();

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $this->activeAward($term, $campus);

        $this->actingAs($staff)
            ->get(route('staff.reports.suc-levelling.export', $term))
            ->assertOk();

        Excel::assertDownloaded('suc-levelling-2026-2027-first.xlsx');
    }
}
