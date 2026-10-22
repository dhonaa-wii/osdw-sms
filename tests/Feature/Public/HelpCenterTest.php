<?php

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\HelpCenterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use RuntimeException;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_the_help_center(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertSee('How can we help?')
            ->assertSee('For students')
            ->assertSee('For OSDW staff')
            ->assertSee('For administrators')
            ->assertSee('Download PDF');
    }

    public function test_every_layout_links_to_the_help_center(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('help').'#students', false);
        $this->get(route('programs.index'))->assertOk()->assertSee(route('help').'#students', false);

        $this->actingAs(User::factory()->create(['role' => UserRole::OsdwStaff, 'password_changed_at' => now()]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('help').'#staff', false);
    }

    public function test_the_help_link_opens_the_administrator_chapter_for_administrators(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('help').'#administrators', false);
    }

    public function test_download_pdf_sends_the_guide_as_a_pdf_file(): void
    {
        $pdf = tempnam(sys_get_temp_dir(), 'help').'.pdf';
        file_put_contents($pdf, '%PDF-1.7 test');
        $this->mock(HelpCenterPdfService::class)->shouldReceive('path')->andReturn($pdf);

        $this->get(route('help.download'))
            ->assertOk()
            ->assertDownload('OSDW Help Center.pdf');
    }

    public function test_download_pdf_reports_when_the_pdf_cant_be_made(): void
    {
        $this->mock(HelpCenterPdfService::class)->shouldReceive('path')->andThrow(new RuntimeException('No Chrome'));

        $this->get(route('help.download'))->assertStatus(503);
    }

    public function test_the_guide_has_a_download_pdf_button(): void
    {
        $this->get(route('help'))->assertOk()->assertSee((string) Js::from(route('help.download')), false);
    }

    public function test_signed_in_users_read_the_same_guide_with_a_link_back(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::OsdwStaff, 'password_changed_at' => now()]))
            ->get(route('help'))
            ->assertOk()
            ->assertSee(route('dashboard'), false);
    }
}
