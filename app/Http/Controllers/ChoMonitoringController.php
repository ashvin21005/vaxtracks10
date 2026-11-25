<?php

namespace App\Http\Controllers;

use App\Models\ImmunizationRecord;
use App\Models\Vaccine;
use App\Models\VaccineInventory;
use Illuminate\Http\Request;

class ChoMonitoringController extends Controller
{
    public function forecast(Request $request)
    {
        $vaccineNames = Vaccine::where('is_active', true)->orderBy('name')->pluck('name');
        $vaccine = $request->query('vaccine');
        if (! $vaccineNames->contains($vaccine)) {
            $vaccine = null;
        }
        $period = (int) $request->query('period', 90);
        if (! in_array($period, [90, 180, 365], true)) {
            $period = 90;
        }

        $start = today()->subDays($period);
        $consumptionQuery = ImmunizationRecord::whereDate('administered_at', '>=', $start);
        if ($vaccine) {
            $consumptionQuery->where('vaccine', $vaccine);
        }
        $consumed = (clone $consumptionQuery)->count();
        $stockQuery = VaccineInventory::query();
        if ($vaccine) {
            $stockQuery->where('vaccine', $vaccine);
        }
        $stock = (clone $stockQuery)->sum('quantity');
        $monthlyEstimate = $period ? (int) ceil(($consumed / $period) * 30 * 1.15) : 0;

        $rows = $vaccineNames->when($vaccine, fn ($rows) => $rows->filter(fn ($name) => $name === $vaccine))
            ->map(function (string $name) use ($period) {
                $historical = ImmunizationRecord::where('vaccine', $name)->whereDate('administered_at', '>=', today()->subDays($period))->count();
                $currentStock = VaccineInventory::where('vaccine', $name)->sum('quantity');
                $demand = (int) ceil(($historical / $period) * 30 * 1.15);

                return [
                    'vaccine' => $name,
                    'historical' => $historical,
                    'stock' => $currentStock,
                    'next_month' => $demand,
                    'future' => $demand * 3,
                    'bar' => min(100, $demand ? max(8, (int) round($demand / max(1, $demand + $currentStock) * 100)) : 4),
                ];
            })->values();

        return view('cho.forecast', compact('vaccine', 'period', 'consumed', 'stock', 'monthlyEstimate', 'rows'));
    }
}
