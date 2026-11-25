<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SprintOneNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_open_sprint_one_modules(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'BHW', 'must_change_password' => false]));

        $this->get(route('dashboard'))->assertRedirect(route('bhw.dashboard'));
        $this->get(route('bhw.dashboard'))->assertOk();
        $this->get(route('patients.index'))->assertOk();
        $this->get(route('immunizations.index'))->assertOk()->assertSee('Immunization Records');
        $this->get(route('schedule.index'))->assertOk()->assertSee('Vaccination Schedule');
        $this->get(route('inventory.index'))->assertOk()->assertSee('Vaccine Inventory');
        $this->get(route('sms.index'))->assertOk()->assertSee('SMS Monitoring');
    }

    public function test_user_can_sign_in_with_name_as_username(): void
    {
        $user = User::factory()->create([
            'name' => 'bhw.demo',
            'email' => 'bhw@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('login.submit'), [
            'login' => 'bhw.demo',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
