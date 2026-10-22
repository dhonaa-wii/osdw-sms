<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeploymentSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeder_loads_reference_data_without_accounts(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);

        $this->assertSame(7, College::count());
        $this->assertSame(0, User::count());
    }

    public function test_create_admin_command_makes_an_administrator(): void
    {
        $this->artisan('osdw:create-admin')
            ->expectsQuestion('Name', 'Maria Santos')
            ->expectsQuestion('Email', 'admin@csu.edu.ph')
            ->expectsQuestion('Password (at least 8 characters)', 'secret-pass')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@csu.edu.ph')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('secret-pass', $admin->password));
    }

    public function test_create_admin_command_refuses_a_taken_email(): void
    {
        User::factory()->create(['email' => 'admin@csu.edu.ph']);

        $this->artisan('osdw:create-admin')
            ->expectsQuestion('Name', 'Maria Santos')
            ->expectsQuestion('Email', 'admin@csu.edu.ph')
            ->assertFailed();
    }
}
