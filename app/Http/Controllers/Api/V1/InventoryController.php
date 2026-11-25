<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VaccineInventoryResource;
use App\Models\Vaccine;
use App\Models\VaccineInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'station' => ['nullable', 'integer', 'exists:health_stations,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = VaccineInventory::query()
            ->with(['healthStation', 'vaccineDefinition'])
            ->when(
                $request->user()->isBhw(),
                fn ($stocks) => $stocks->where('health_station_id', $request->user()->health_station_id ?: -1)
            )
            ->when(
                $request->user()->isCho() && $request->query('station'),
                fn ($stocks) => $stocks->where('health_station_id', $filters['station'])
            )
            ->when(trim($filters['search'] ?? '') !== '', function ($stocks) use ($filters) {
                $search = trim($filters['search']);
                $stocks->where('vaccine', 'like', "%{$search}%");
            })
            ->orderBy('vaccine');

        return VaccineInventoryResource::collection(
            $query->paginate($filters['per_page'] ?? 15)->withQueryString()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vaccine_id' => ['required', 'integer', Rule::exists('vaccines', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'health_station_id' => ['nullable', 'integer', 'exists:health_stations,id'],
        ]);
        $stationId = $data['health_station_id'] ?? null;
        $vaccine = Vaccine::findOrFail($data['vaccine_id']);
        [$stock, $created] = DB::transaction(function () use ($stationId, $vaccine, $data) {
            $query = VaccineInventory::where('vaccine_id', $vaccine->id);
            $stationId === null
                ? $query->whereNull('health_station_id')
                : $query->where('health_station_id', $stationId);
            $stock = $query->lockForUpdate()->first();
            if ($stock) {
                $stock->increment('quantity', $data['quantity']);

                return [$stock->fresh(), false];
            }

            return [VaccineInventory::create([
                'health_station_id' => $stationId,
                'vaccine_id' => $vaccine->id,
                'vaccine' => $vaccine->name,
                'quantity' => $data['quantity'],
                'reorder_level' => $data['reorder_level'],
            ]), true];
        });

        return (new VaccineInventoryResource($stock->load(['healthStation', 'vaccineDefinition'])))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function adjust(Request $request, VaccineInventory $stock)
    {
        abort_unless($request->user()->isCho(), 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['add'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $stock = DB::transaction(function () use ($stock, $data) {
            $locked = VaccineInventory::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $locked->increment('quantity', $data['quantity']);

            return $locked->fresh()->load(['healthStation', 'vaccineDefinition']);
        });

        return new VaccineInventoryResource($stock);
    }

}
