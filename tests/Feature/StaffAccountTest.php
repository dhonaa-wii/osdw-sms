<?php

namespace Tests\Feature;

use App\Enums\FundingSource;
use App\Enums\NotificationKind;
use App\Enums\UserRole;
use App\Models\Provider;
use App\Models\User;
use App\Notifications\StaffNotification;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    protected function newStaff(): User
    {
        return User::factory()->temporaryPassword()->create([
            'email' => 'new.staff@osdw.test',
            'password' => 'Temp-pass-123',
            'role' => UserRole::OsdwStaff,
        ]);
    }

    protected function google(string $id, string $email): GoogleUser
    {
        return (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => 'Someone']);
    }

    public function test_admin_adds_staff_with_a_generated_password(): void
    {
        Livewire::actingAs($this->admin())
            ->test('admin::user-management.index')
            ->set('name', 'Ana Reyes')
            ->set('email', 'ana@osdw.test')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notice', fn ($name, $params) => str_contains($params['message'], 'Temporary password:'));

        $staff = User::where('email', 'ana@osdw.test')->first();

        $this->assertSame(UserRole::OsdwStaff, $staff->role);
        $this->assertNull($staff->password_changed_at);
        $this->assertTrue($staff->needsAccountSetup());
    }

    public function test_generated_password_signs_in_and_reset_requires_a_new_change(): void
    {
        $accounts = app(AccountService::class);
        [$staff, $password] = $accounts->createStaff('Ana Reyes', 'ana@osdw.test');

        $this->assertTrue(Hash::check($password, $staff->password));

        $accounts->setPassword($staff, 'my-own-password');
        $this->assertFalse($staff->fresh()->needsAccountSetup());

        $accounts->resetStaffPassword($staff);
        $this->assertTrue($staff->fresh()->needsAccountSetup());
    }

    public function test_first_login_goes_to_setup_and_staff_pages_are_blocked(): void
    {
        $this->newStaff();

        Livewire::test('auth::login')
            ->set('email', 'new.staff@osdw.test')
            ->set('password', 'Temp-pass-123')
            ->call('login')
            ->assertRedirect(route('staff.account.setup'));

        $this->get(route('dashboard'))->assertRedirect(route('staff.account.setup'));
        $this->get(route('staff.applications.index'))->assertRedirect(route('staff.account.setup'));
        $this->get(route('staff.account.setup'))->assertOk()->assertSee('Set your own password');
    }

    public function test_setup_requires_a_new_password_then_opens_the_dashboard(): void
    {
        $staff = $this->newStaff();

        Livewire::actingAs($staff)
            ->test('staff::account-setup')
            ->set('password', 'Temp-pass-123')
            ->set('password_confirmation', 'Temp-pass-123')
            ->call('savePassword')
            ->assertHasErrors('password')
            ->set('password', 'a-better-password')
            ->set('password_confirmation', 'a-better-password')
            ->call('savePassword')
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($staff->fresh()->password_changed_at);
    }

    public function test_setup_also_requires_google_when_google_sign_in_is_configured(): void
    {
        config(['services.google.client_id' => 'test-client']);

        $staff = $this->newStaff();
        app(AccountService::class)->setPassword($staff, 'a-better-password');

        $this->assertTrue($staff->fresh()->needsAccountSetup());

        app(AccountService::class)->linkGoogle($staff, $this->google('g-123', 'ana.reyes@gmail.com'));

        $this->assertFalse($staff->fresh()->needsAccountSetup());
        $this->assertSame('g-123', $staff->fresh()->google_id);
    }

    public function test_a_google_account_used_by_another_account_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@gmail.com', 'role' => UserRole::Student]);
        $staff = $this->newStaff();

        $this->expectException(\RuntimeException::class);

        app(AccountService::class)->linkGoogle($staff, $this->google('g-456', 'taken@gmail.com'));
    }

    public function test_staff_edits_name_and_password_on_profile(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff, 'password' => 'old-password']);

        Livewire::actingAs($staff)
            ->test('staff::profile')
            ->set('name', 'Ana M. Reyes')
            ->call('saveName')
            ->assertDispatched('notice')
            ->set('current_password', 'wrong')
            ->set('password', 'new-password-1')
            ->set('password_confirmation', 'new-password-1')
            ->call('savePassword')
            ->assertHasErrors('current_password')
            ->set('current_password', 'old-password')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertSame('Ana M. Reyes', $staff->fresh()->name);
        $this->assertTrue(Hash::check('new-password-1', $staff->fresh()->password));
    }

    public function test_a_new_scholarship_program_reaches_every_staff_bell(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $provider = Provider::create(['name' => 'CHED', 'funding_source' => FundingSource::cases()[0]]);

        Livewire::actingAs($admin)
            ->test('admin::program-configuration.scholarship-programs')
            ->set('name', 'Merit Scholarship')
            ->set('provider_id', (string) $provider->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(NotificationKind::ProgramCreated->value, $staff->notifications()->first()?->data['kind']);
        $this->assertSame('/staff/applications', $staff->notifications()->first()->data['url']);
        $this->assertStringStartsWith('/admin/scholarship-programs/', $admin->notifications()->first()->data['url']);
    }

    public function test_staff_bell_and_notifications_page_show_staff_notifications(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OsdwStaff]);
        $staff->notify(new StaffNotification(NotificationKind::ProgramCreated, 'New scholarship program: Merit', 'Provided by CHED.', '/staff/applications'));

        $this->actingAs($staff)->get(route('dashboard'))->assertOk();

        Livewire::actingAs($staff)->test('staff::notification-bell')->assertSee('New scholarship program: Merit');
        Livewire::actingAs($staff)->test('staff::notifications')
            ->assertSee('New scholarship program: Merit')
            ->call('markAllRead');

        $this->assertSame(0, $staff->unreadNotifications()->count());
    }
}
