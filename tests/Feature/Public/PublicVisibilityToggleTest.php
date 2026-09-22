<?php

namespace Tests\Feature\Public;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicVisibilityToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_is_reachable_by_default(): void
    {
        Livewire::test('public::results')->assertDontSee('This page is temporarily unavailable');
    }

    public function test_results_page_is_hidden_when_setting_disabled(): void
    {
        Setting::create(['key' => 'public_results_visible', 'value' => '0']);

        Livewire::test('public::results')->assertSee('This page is temporarily unavailable');
    }
}
