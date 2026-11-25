<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cho_and_bhw_are_redirected_to_their_own_dashboards(): void
    {
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $this->actingAs($cho)->get(route('dashboard'))->assertRedirect(route('cho.dashboard'));
        $this->actingAs($cho)->get(route('bhw.dashboard'))->assertForbidden();
        $this->get(route('patients.index'))->assertForbidden();
        $this->get(route('patients.create'))->assertForbidden();
        $this->post(route('patients.store'))->assertForbidden();
        $this->get(route('immunizations.index'))->assertForbidden();
        $this->get(route('immunizations.create'))->assertForbidden();
        $this->post(route('immunizations.store'))->assertForbidden();
        $this->get(route('schedule.index'))->assertForbidden();
        $this->get(route('sms.index'))->assertForbidden();
        $this->post('/vaccination-schedule')->assertStatus(405);
        $this->get(route('cho.dashboard'))->assertOk()
            ->assertDontSee('Patients')
            ->assertDontSee('Immunization Records')
            ->assertDontSee('Vaccination Schedule')
            ->assertDontSee('SMS Monitoring')
            ->assertDontSee('Notifications and SMS monitoring')
            ->assertDontSee('Recent city-wide immunization activity');

        $bhw = User::factory()->create(['role' => 'BHW', 'must_change_password' => false]);
        $this->actingAs($bhw)->get(route('dashboard'))->assertRedirect(route('bhw.dashboard'));
        $this->actingAs($bhw)->get(route('bhw.dashboard'))->assertOk();
        $this->get(route('schedule.index'))->assertOk()->assertSee('Vaccination Schedule');
        $this->actingAs($bhw)->get(route('cho.dashboard'))->assertForbidden();
    }

    public function test_inactive_accounts_cannot_log_in(): void
    {
        User::factory()->create([
            'username' => 'disabled.user',
            'account_status' => 'INACTIVE',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('login.submit'), [
            'login' => 'disabled.user',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_deactivated_account_loses_an_existing_session(): void
    {
        $user = User::factory()->create(['role' => 'BHW', 'must_change_password' => false]);
        $this->actingAs($user)->get(route('bhw.dashboard'))->assertOk();
        $user->update(['account_status' => 'INACTIVE']);

        $this->get(route('bhw.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_first_login_requires_password_change_before_dashboard(): void
    {
        User::factory()->create([
            'username' => 'first.login',
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
        ]);

        $this->post(route('login.submit'), [
            'login' => 'first.login',
            'password' => 'temporary-password',
        ])->assertRedirect(route('password.change'));

        $this->get(route('bhw.dashboard'))->assertRedirect(route('password.change'));

        $this->put(route('password.update'), [
            'current_password' => 'temporary-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['username' => 'first.login', 'must_change_password' => false]);
    }
}
