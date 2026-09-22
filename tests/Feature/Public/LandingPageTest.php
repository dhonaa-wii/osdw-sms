<?php

namespace Tests\Feature\Public;

use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\Campus;
use App\Models\ProgramOffering;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_loads_for_a_guest(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Browse open scholarships')
            ->assertSee('Track your application');
    }

    public function test_landing_page_shows_open_offerings_and_live_counts(): void
    {
        Campus::create(['name' => 'Aparri']);
        $provider = Provider::create(['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'is_active' => true]);
        $scholarshipProgram = ScholarshipProgram::create([
            'name' => 'DOST-SEI Fellowship', 'provider_id' => $provider->id,
            'category' => ScholarshipCategory::Academic,
        ]);
        $term = AcademicTerm::create([
            'school_year' => '2026-2027', 'semester' => Semester::First,
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        ProgramOffering::create([
            'scholarship_program_id' => $scholarshipProgram->id, 'academic_term_id' => $term->id, 'campus_id' => Campus::first()->id,
            'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'total_slots' => 20,
            'college_scope' => 'all', 'allocation_mode' => 'open',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('DOST-SEI Fellowship')
            ->assertSee('Aparri');
    }

    public function test_landing_page_shows_published_general_announcements_only(): void
    {
        $staffId = User::factory()->create()->id;
        Announcement::create(['title' => 'Deadline extended', 'body' => 'The deadline has been moved.', 'is_published' => true, 'published_at' => now(), 'created_by' => $staffId]);
        Announcement::create(['title' => 'Draft note', 'body' => 'Not ready yet.', 'is_published' => false, 'created_by' => $staffId]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Deadline extended')
            ->assertDontSee('Draft note');
    }
}
