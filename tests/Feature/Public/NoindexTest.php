<?php

namespace Tests\Feature\Public;

use App\Models\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoindexTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_is_noindexed(): void
    {
        $this->get(route('results.index'))->assertSee('noindex, nofollow', false);
    }

    public function test_track_dashboard_is_noindexed(): void
    {
        $applicant = Applicant::create([
            'student_id' => 'SID-000001', 'last_name' => 'Dela Cruz', 'first_name' => 'Juan',
            'sex' => 'male', 'email' => 'juan@example.com',
        ]);
        $this->withSession(['tracking_applicant_id' => $applicant->id])
            ->get(route('track.dashboard'))
            ->assertSee('noindex, nofollow', false);
    }

    public function test_browse_page_is_indexable(): void
    {
        $this->get(route('programs.index'))->assertDontSee('noindex', false);
    }
}
