<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baby_book_id' => $this->baby_book_id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'sex' => $this->sex,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'time_of_birth' => $this->time_of_birth,
            'place_of_birth' => $this->place_of_birth,
            'place_of_birth_type' => $this->place_of_birth_type,
            'place_of_birth_details' => $this->place_of_birth_details,
            'birth_facility' => $this->birth_facility,
            'birth_weight' => $this->birth_weight,
            'purok' => $this->purok,
            'street' => $this->street,
            'barangay' => $this->barangay,
            'address' => $this->address,
            'status' => $this->status,
            'health_station_id' => $this->health_station_id,
            'archived_at' => $this->deleted_at,
            'guardian' => $this->whenLoaded('guardian', fn () => new GuardianResource($this->guardian)),
            'health_station' => $this->whenLoaded('healthStation', fn () => [
                'id' => $this->healthStation->id,
                'name' => $this->healthStation->name,
                'barangay' => $this->healthStation->barangay,
            ]),
            'immunization_records' => $this->whenLoaded('immunizationRecords', fn () => ImmunizationRecordResource::collection($this->immunizationRecords)),
            'vaccination_schedules' => $this->whenLoaded('vaccinationSchedules', fn () => VaccinationScheduleResource::collection($this->vaccinationSchedules)),
        ];
    }
}
