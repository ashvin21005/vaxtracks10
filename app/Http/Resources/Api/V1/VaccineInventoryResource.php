<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaccineInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'health_station_id' => $this->health_station_id,
            'health_station' => $this->whenLoaded('healthStation', fn () => $this->healthStation ? [
                'id' => $this->healthStation->id,
                'name' => $this->healthStation->name,
            ] : null),
            'vaccine_id' => $this->vaccine_id,
            'vaccine' => $this->vaccineDefinition?->name ?? $this->vaccine,
            'quantity' => $this->quantity,
            'reorder_level' => $this->reorder_level,
            'stock_status' => $this->stock_status,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
