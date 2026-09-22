<?php

namespace Tests\Feature\Staff;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ShortlistExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_export_the_shortlist(): void
    {
        Excel::fake();

        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open',
        ]);
        $college = College::create(['code' => 'CICS', 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
        $applicant->applications()->create([
            'reference_no' => 'SCH-2026-'.Str::random(6), 'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'shortlisted',
            'submitted_at' => now(),
        ]);

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);

        $this->actingAs($staff)
            ->get(route('staff.applications.workspace.shortlist.export', $offering))
            ->assertOk();

        Excel::assertDownloaded('shortlist-dost-sei-fellowship.xlsx');
    }

    public function test_guest_cannot_export_the_shortlist(): void
    {
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $offering = ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open',
        ]);

        $this->get(route('staff.applications.workspace.shortlist.export', $offering))
            ->assertRedirect(route('login'));
    }
}
