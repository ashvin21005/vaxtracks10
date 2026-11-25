<?php

namespace App\Http\Controllers;

use App\Models\HealthStation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BhwManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('healthStation')->where('role', 'BHW')->orderBy('name');
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(fn ($users) => $users->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->query('station')) {
            $query->where('health_station_id', $request->query('station'));
        }
        if ($request->query('status')) {
            $query->where('account_status', $request->query('status'));
        }

        return view('cho.bhws.index', [
            'users' => $query->paginate(15)->withQueryString(),
            'stations' => HealthStation::with('barangays')->orderBy('name')->get(),
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('cho.bhws.form', ['stations' => HealthStation::with('barangays')->where('status', 'ACTIVE')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'regex:/^[A-Za-z0-9._-]+$/', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'health_station_id' => ['required', 'exists:health_stations,id'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $station = HealthStation::findOrFail($data['health_station_id']);
        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'BHW',
            'account_status' => 'ACTIVE',
            'health_station_id' => $station->id,
            'barangay' => $station->barangay,
            'must_change_password' => true,
        ]);

        return redirect()->route('cho.bhws.index')->with('success', 'BHW account created. They must change the temporary password at first login.');
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->role === 'BHW', 404);
        $data = $request->validate([
            'health_station_id' => ['required', 'exists:health_stations,id'],
            'account_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
        $station = HealthStation::findOrFail($data['health_station_id']);
        $user->update($data + ['barangay' => $station->barangay]);

        return redirect()->route('cho.bhws.index')->with('success', 'BHW account updated.');
    }

    public function destroy(User $user)
    {
        abort_unless($user->role === 'BHW', 404);
        $user->delete();

        return redirect()->route('cho.bhws.index')->with('success', 'BHW account deleted. Existing immunization records have been kept.');
    }
}
