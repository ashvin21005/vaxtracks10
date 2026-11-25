<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\HealthStation;
use App\Models\VaccinationSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HealthStationController extends Controller
{
    public function index(Request $request)
    {
        $query = HealthStation::with('barangays')->withCount([
            'children',
            'users as bhw_count' => fn ($users) => $users->where('role', 'BHW'),
        ])->orderBy('name');
        $search = trim((string) $request->query('search', ''));
        $barangay = $request->query('barangay');
        $status = $request->query('status');
        if ($search !== '') {
            $query->where(fn ($stations) => $stations->where('name', 'like', "%{$search}%")
                ->orWhere('barangay', 'like', "%{$search}%")
                ->orWhereHas('barangays', fn ($areas) => $areas->where('official_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")));
        }
        if ($barangay) {
            $query->where(fn ($stations) => $stations->where('barangay', $barangay)
                ->orWhereHas('barangays', fn ($areas) => $areas->where('official_name', $barangay)));
        }
        if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $query->where('status', $status);
        }
        $stations = $query->paginate(15)->withQueryString();
        foreach ($stations as $station) {
            $counts = $this->scheduleCounts($station);
            $station->due_count = $counts->get('DUE', 0);
            $station->overdue_count = $counts->get('OVERDUE', 0);
            $station->low_stock_count = $station->vaccineInventory()->whereColumn('quantity', '<=', 'reorder_level')->count();
        }

        return view('cho.stations.index', [
            'stations' => $stations,
            'search' => $search,
            'barangays' => Barangay::query()->orderBy('official_name')->get(['id', 'official_name', 'name']),
            'barangay' => $barangay,
            'status' => $status,
        ]);
    }

    public function create()
    {
        request()->merge(['create' => 1]);

        return $this->index(request());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:health_stations,name'],
            'barangays' => ['required', 'array', 'min:1'],
            'barangays.*' => ['required', 'integer', 'distinct', Rule::exists('barangays', 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
        $firstBarangay = Barangay::findOrFail($data['barangays'][0]);
        $station = DB::transaction(function () use ($data, $firstBarangay) {
            $station = HealthStation::create([
                'name' => $data['name'],
                'barangay' => $firstBarangay->official_name,
                'address' => $data['address'] ?? null,
                'status' => $data['status'],
            ]);
            $station->barangays()->sync($data['barangays']);

            return $station;
        });

        return redirect()->route('cho.stations.show', $station)->with('success', 'Health station created.');
    }

    public function edit(HealthStation $station)
    {
        $stationPosition = HealthStation::where('name', '<=', $station->name)->count();
        $page = max(1, (int) ceil($stationPosition / 15));

        return redirect()->route('cho.stations.index', ['edit' => $station->id, 'page' => $page]);
    }

    public function update(Request $request, HealthStation $station)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('health_stations', 'name')->ignore($station->id)],
            'barangays' => ['required', 'array', 'min:1'],
            'barangays.*' => ['required', 'integer', 'distinct', Rule::exists('barangays', 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
        $firstBarangay = Barangay::findOrFail($data['barangays'][0]);

        DB::transaction(function () use ($station, $data, $firstBarangay) {
            $station->update([
                'name' => $data['name'],
                'barangay' => $firstBarangay->official_name,
                'address' => $data['address'] ?? null,
                'status' => $data['status'],
            ]);
            $station->barangays()->sync($data['barangays']);
        });

        return redirect()->route('cho.stations.index')->with('success', 'Health station updated.');
    }

    public function destroy(HealthStation $station)
    {
        if ($station->users()->exists() || $station->children()->exists() || $station->vaccineInventory()->exists()) {
            return redirect()->route('cho.stations.index')
                ->with('error', 'This health station still has assigned BHWs, children, or inventory. Reassign or remove those records before deleting the station.');
        }

        $station->delete();

        return redirect()->route('cho.stations.index')->with('success', 'Health station deleted.');
    }

    public function show(HealthStation $station)
    {
        $station->load(['barangays', 'users' => fn ($users) => $users->where('role', 'BHW')->orderBy('name'), 'vaccineInventory'])
            ->loadCount('children');
        $counts = $this->scheduleCounts($station);
        $station->due_count = $counts->get('DUE', 0);
        $station->overdue_count = $counts->get('OVERDUE', 0);

        return view('cho.stations.show', compact('station'));
    }

    private function scheduleCounts(HealthStation $station)
    {
        return VaccinationSchedule::with('vaccineDefinition')
            ->whereHas('child', fn ($children) => $children->where('health_station_id', $station->id))
            ->get()
            ->countBy(fn ($schedule) => $schedule->display_status);
    }
}
