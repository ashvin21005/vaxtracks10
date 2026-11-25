<?php

namespace App\Services;

use App\Models\Vaccine;
use App\Models\VaccineInventory;
use Illuminate\Validation\ValidationException;

class VaccineInventoryService
{
    public function deductDose(int $healthStationId, Vaccine $vaccine): VaccineInventory
    {
        $stock = VaccineInventory::query()
            ->where('health_station_id', $healthStationId)
            ->where('vaccine_id', $vaccine->id)
            ->lockForUpdate()
            ->first();

        if (! $stock || $stock->quantity < 1) {
            throw ValidationException::withMessages([
                'inventory' => $stock
                    ? "Not enough {$vaccine->name} stock is available at your health station."
                    : "No {$vaccine->name} stock is recorded for your health station.",
            ]);
        }

        $stock->decrement('quantity');

        return $stock->refresh();
    }
}
