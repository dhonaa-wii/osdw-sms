<?php

namespace Tests\Feature\Staff;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Mail\IncompleteApplicationMail;
use App\Models\AcademicTerm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function applicationWithDocument(ProgramOffering $offering, string $documentStatus): Application
    {
        $field = $offering->formFields()->create([
            'field_type' => 'file', 'label' => 'Certificate of Registration',
            'is_required' => true, 'accepted_formats' => 'pdf', 'max_size_mb' => 5, 'sort_order' => 1,
        ]);
        $college = College::create(['code' => 'C'.Str::random(3), 'name' => 'College of ICS']);
        $applicant = Applicant::create([
            'student_id' => 'SID-'.Str::random(6), 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => Str::random(6).'@example.com',
        ]);
        $application = $applicant->applications()->create([
            'access_token' => str_repeat('a', 64),
            'program_offering_id' => $offering->id, 'college_id' => $college->id,
            'year_level' => 2, 'status' => 'screening', 'submitted_at' => now(),
        ]);
        $application->documents()->create([
            'offering_form_field_id' => $field->id, 'file_path' => 'applications/test/cor.pdf',
            'original_name' => 'cor.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1000, 'status' => $documentStatus,
        ]);

        return $application;
    }

    private function programOffering(): ProgramOffering
    {
        $provider = Provider::create(['name' => 'DOST-SEI '.Str::random(4), 'funding_source' => FundingSource::NationalGovernment]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $campus = Campus::create(['name' => 'Aparri']);

        return ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00', 'closes_at' => '2026-09-10 17:00', 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open', 'category' => ScholarshipCategory::Academic,
        ]);
    }

    public function test_bulk_verify_marks_pending_documents_verified(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $application = $this->applicationWithDocument($offering, 'pending');

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->set('selected', [$application->id])
            ->call('bulkVerify');

        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'status' => 'verified',
        ]);
    }

    public function test_bulk_notify_emails_applicants_with_a_rejected_document(): void
    {
        Mail::fake();

        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $offering = $this->programOffering();
        $rejectedApplication = $this->applicationWithDocument($offering, 'rejected');
        $pendingApplication = $this->applicationWithDocument($offering, 'pending');

        Livewire::actingAs($staff)
            ->test('staff::applications.workspace', ['offering' => $offering])
            ->set('selected', [$rejectedApplication->id, $pendingApplication->id])
            ->call('bulkNotifyIncomplete');

        Mail::assertSent(IncompleteApplicationMail::class, 1);
        $this->assertDatabaseHas('email_logs', ['application_id' => $rejectedApplication->id]);
        $this->assertDatabaseMissing('email_logs', ['application_id' => $pendingApplication->id]);
    }
}
