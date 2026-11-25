<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Barangay;
use App\Models\Guardian;
use App\Models\HealthStation;
use App\Models\ImmunizationRecord;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\VaccineInventory;
use Database\Seeders\ChoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChoAdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cho_monitoring_pages_show_live_data_and_filters(): void
    {
        $station = HealthStation::create(['name' => 'Central Health Station', 'barangay' => 'Central']);
        $user = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $guardian = Guardian::create(['first_name' => 'Lina', 'last_name' => 'Cruz', 'relationship' => 'Mother', 'contact_number' => '09171234567']);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'health_station_id' => $station->id,
            'first_name' => 'Noah',
            'last_name' => 'Cruz',
            'sex' => 'Male',
            'date_of_birth' => today()->subMonths(6)->toDateString(),
            'barangay' => 'Central',
            'status' => 'ACTIVE',
        ]);
        ImmunizationRecord::create(['child_id' => $child->id, 'vaccine' => 'BCG', 'dose_number' => 1, 'administered_at' => today(), 'recorded_by' => $user->id]);
        VaccineInventory::create(['health_station_id' => $station->id, 'vaccine' => 'OPV', 'quantity' => 2, 'reorder_level' => 10]);

        $this->actingAs($user);
        $this->get(route('cho.dashboard'))
            ->assertOk()
            ->assertSee('Central Health Station')
            ->assertSee('1')
            ->assertDontSee('Outbreak Risk')
            ->assertDontSee('Barangay outbreak risk');
        $this->get(route('cho.forecast', ['vaccine' => 'BCG']))->assertOk()->assertSee('BCG');
        $this->get('/cho/outbreak-risk')->assertNotFound();
        $this->get(route('cho.stations.index', ['barangay' => 'Central']))->assertOk()->assertSee('Central Health Station');
        $this->get(route('cho.stations.show', $station))
            ->assertOk()
            ->assertSee('Registered children')
            ->assertSee('1')
            ->assertDontSee('Noah Cruz');
    }

    public function test_cho_can_create_stations_and_provision_bhw_accounts(): void
    {
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $firstBarangay = Barangay::create([
            'official_name' => 'Barangay 1',
            'name' => 'Libertad',
            'city' => 'Tacloban City',
            'province' => 'Leyte',
        ]);
        $secondBarangay = Barangay::create([
            'official_name' => 'Barangay 2',
            'name' => 'San Jose',
            'city' => 'Tacloban City',
            'province' => 'Leyte',
        ]);
        $this->actingAs($cho);
        $this->get(route('cho.bhws.create'))->assertOk();
        $this->get(route('cho.stations.create'))
            ->assertOk()
            ->assertSee('class="station-barangay-fieldset"', false)
            ->assertSee('type="checkbox" name="barangays[]"', false)
            ->assertSee('data-barangay-search', false)
            ->assertSee('Find a barangay')
            ->assertSee('Barangay 1 — Libertad')
            ->assertSee('Barangay 2 — San Jose');

        $this->post(route('cho.stations.store'), [
            'name' => 'East Health Station',
            'barangays' => [$firstBarangay->id, $secondBarangay->id],
            'status' => 'ACTIVE',
        ])->assertRedirect();
        $station = HealthStation::where('name', 'East Health Station')->firstOrFail();
        $this->assertSame('Barangay 1', $station->barangay);
        $this->assertSame(['Barangay 1', 'Barangay 2'], $station->barangays->pluck('official_name')->all());
        $this->get(route('cho.stations.show', $station))
            ->assertOk()
            ->assertSee('Barangay 1, Barangay 2');
        $this->get(route('cho.stations.index', ['edit' => $station->id]))
            ->assertOk()
            ->assertSee('health-station-edit-'.$station->id, false)
            ->assertSee('Edit East Health Station');
        $this->get(route('cho.stations.edit', $station))
            ->assertRedirect(route('cho.stations.index', ['edit' => $station->id, 'page' => 1]));

        $this->put(route('cho.stations.update', $station), [
            'name' => 'East Health Station',
            'barangays' => [$secondBarangay->id],
            'status' => 'ACTIVE',
        ])->assertRedirect(route('cho.stations.index'));
        $this->assertSame(['Barangay 2'], $station->fresh()->barangays->pluck('official_name')->all());
        $this->assertSame('Barangay 2', $station->fresh()->barangay);

        $this->from(route('cho.stations.create'))
            ->post(route('cho.stations.store'), [
                'name' => 'Invalid Health Station',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasErrors('barangays');

        $this->post(route('cho.bhws.store'), [
            'name' => 'BHW East',
            'username' => 'bhw.east',
            'email' => 'bhw.east@example.test',
            'health_station_id' => $station->id,
            'password' => 'Temporary-Password-321',
            'password_confirmation' => 'Temporary-Password-321',
        ])->assertRedirect(route('cho.bhws.index'));

        $bhw = User::where('username', 'bhw.east')->firstOrFail();
        $this->assertSame('BHW', $bhw->role);
        $this->assertSame('ACTIVE', $bhw->account_status);
        $this->assertTrue($bhw->must_change_password);
        $this->assertTrue(Hash::check('Temporary-Password-321', $bhw->password));
        $this->assertSame($station->id, $bhw->health_station_id);
    }

    public function test_cho_can_delete_bhw_account_without_deleting_immunization_history(): void
    {
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $bhw = User::factory()->create(['role' => 'BHW', 'must_change_password' => false]);
        $guardian = Guardian::create([
            'first_name' => 'Lina',
            'last_name' => 'Cruz',
            'relationship' => 'Mother',
            'contact_number' => '09171234567',
        ]);
        $child = Child::create([
            'guardian_id' => $guardian->id,
            'first_name' => 'Noah',
            'last_name' => 'Cruz',
            'sex' => 'Male',
            'date_of_birth' => today()->subMonths(6)->toDateString(),
            'barangay' => 'Central',
        ]);
        $record = ImmunizationRecord::create([
            'child_id' => $child->id,
            'vaccine' => 'BCG',
            'dose_number' => 1,
            'administered_at' => today(),
            'recorded_by' => $bhw->id,
        ]);

        $this->actingAs($cho)
            ->get(route('cho.bhws.index'))
            ->assertOk()
            ->assertSee('Delete');
        $this->delete(route('cho.bhws.destroy', $bhw))
            ->assertRedirect(route('cho.bhws.index'));

        $this->assertDatabaseMissing('users', ['id' => $bhw->id]);
        $this->assertDatabaseHas('immunization_records', ['id' => $record->id, 'recorded_by' => null]);
    }

    public function test_cho_can_delete_empty_health_stations_but_not_stations_with_linked_data(): void
    {
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $empty = HealthStation::create(['name' => 'Empty Station', 'barangay' => 'Barangay 1']);
        $occupied = HealthStation::create(['name' => 'Occupied Station', 'barangay' => 'Barangay 2']);
        User::factory()->create(['role' => 'BHW', 'health_station_id' => $occupied->id]);

        $this->actingAs($cho)
            ->get(route('cho.stations.index'))
            ->assertOk()
            ->assertSee('Delete');
        $this->delete(route('cho.stations.destroy', $occupied))
            ->assertRedirect(route('cho.stations.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('health_stations', ['id' => $occupied->id]);

        $this->delete(route('cho.stations.destroy', $empty))
            ->assertRedirect(route('cho.stations.index'));
        $this->assertDatabaseMissing('health_stations', ['id' => $empty->id]);
    }

    public function test_cho_can_delete_unused_vaccines_and_is_blocked_from_deleting_vaccines_in_use(): void
    {
        $cho = User::factory()->create(['role' => 'CHO_ADMIN', 'must_change_password' => false]);
        $unused = Vaccine::create(['name' => 'Unused vaccine', 'is_active' => true]);
        $used = Vaccine::create(['name' => 'Used vaccine', 'is_active' => true]);
        $station = HealthStation::create(['name' => 'Central Station', 'barangay' => 'Central']);
        VaccineInventory::create([
            'health_station_id' => $station->id,
            'vaccine_id' => $used->id,
            'vaccine' => $used->name,
            'quantity' => 4,
            'reorder_level' => 1,
        ]);

        $this->actingAs($cho)
            ->get(route('cho.vaccines.index'))
            ->assertOk()
            ->assertSee('Delete');
        $this->delete(route('cho.vaccines.destroy', $used))
            ->assertRedirect(route('cho.vaccines.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('vaccines', ['id' => $used->id]);

        $this->delete(route('cho.vaccines.destroy', $unused))
            ->assertRedirect(route('cho.vaccines.index'));
        $this->assertDatabaseMissing('vaccines', ['id' => $unused->id]);
    }

    public function test_bhw_cannot_access_cho_administration_routes(): void
    {
        $bhw = User::factory()->create(['role' => 'BHW', 'must_change_password' => false]);
        $this->actingAs($bhw)->get(route('cho.dashboard'))->assertForbidden();
        $this->actingAs($bhw)->get(route('cho.forecast'))->assertForbidden();
        $this->actingAs($bhw)->get(route('cho.stations.index'))->assertForbidden();
    }

    public function test_initial_cho_seeder_creates_active_hashed_first_login_account(): void
    {
        app(ChoAdminSeeder::class)->run('Test-CHO-Password-123');

        $admin = User::where('username', 'cho.admin')->firstOrFail();
        $this->assertSame('CHO_ADMIN', $admin->role);
        $this->assertSame('ACTIVE', $admin->account_status);
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(Hash::check('Test-CHO-Password-123', $admin->password));

        $this->post(route('login.submit'), ['login' => 'cho.admin', 'password' => 'Test-CHO-Password-123'])
            ->assertRedirect(route('password.change'));
    }
}
