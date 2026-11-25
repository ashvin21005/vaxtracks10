<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImmunizationRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'child_id' => $this->child_id,
            'vaccine_id' => $this->vaccine_id,
            'vaccine' => $this->whenLoaded('vaccineDefinition', fn () => [
                'id' => $this->vaccineDefinition?->id ?? $this->vaccine_id,
                'name' => $this->vaccineDefinition?->name ?? $this->vaccine,
            ]),
            'dose_number' => $this->dose_number,
            'administered_at' => $this->administered_at?->toDateString(),
            'administered_month' => $this->administered_month,
            'administered_year' => $this->administered_year,
            'date_precision' => $this->date_precision,
            'record_type' => $this->record_type,
            'history_status' => $this->history_status,
            'administering_facility_type' => $this->administering_facility_type,
            'administering_facility_name' => $this->administering_facility_name,
            'verification_source' => $this->verification_source,
            'guardian_reported' => $this->guardian_reported,
            'vaccine_brand' => $this->vaccine_brand,
            'manufacturer' => $this->manufacturer,
            'batch_number' => $this->batch_number,
            'validation_status' => $this->validation_status,
            'status' => $this->status,
            'recorded_by' => $this->recorded_by,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'recorder' => $this->whenLoaded('recorder', fn () => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null),
        ];
    }
}
