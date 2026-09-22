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

class SucLevellingRangeExportTest extends TestCase
{
    use RefreshDatabase;

    private function activeAward(string $awardedAt): Award
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
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 5,
            'college_scope' => 'all', 'allocation_mode' => 'open',
            'amount' => 5000,
        ]);
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $applicant = Applicant::create([
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
            'academic_term_id' => $term->id, 'amount' => 5000, 'awarded_by' => $staff->id, 'awarded_at' => $awardedAt,
            'acceptance_status' => 'accepted', 'accepted_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_date_range_includes_awards_inside_the_range(): void
    {
        $this->activeAward('2026-08-15 10:00:00');

        $rows = app(ReportService::class)->sucLevellingByDateRange('2026-08-01', '2026-08-31');

        $this->assertCount(1, $rows);
    }

    public function test_date_range_excludes_awards_outside_the_range(): void
    {
        $this->activeAward('2026-09-15 10:00:00');

        $rows = app(ReportService::class)->sucLevellingByDateRange('2026-08-01', '2026-08-31');

        $this->assertCount(0, $rows);
    }

    public function test_open_ended_range_includes_everything_from_a_start_date(): void
    {
        $this->activeAward('2026-12-01 10:00:00');

        $rows = app(ReportService::class)->sucLevellingByDateRange('2026-08-01', null);

        $this->assertCount(1, $rows);
    }

    public function test_staff_can_download_the_range_export(): void
    {
        Excel::fake();

        $this->activeAward('2026-08-15 10:00:00');
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)
            ->get(route('staff.reports.suc-levelling.export-range', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk();
    }

    public function test_to_date_before_from_date_is_rejected(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)
            ->get(route('staff.reports.suc-levelling.export-range', ['from' => '2026-08-31', 'to' => '2026-08-01']))
            ->assertSessionHasErrors('to');
    }
}
