<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaccinationScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'child_id' => $this->child_id,
            'child' => $this->whenLoaded('child', fn () => [
                'id' => $this->child->id,
                'full_name' => $this->child->full_name,
            ]),
            'vaccine' => $this->vaccine,
            'dose_number' => $this->dose_number,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->display_status,
            'review_required' => $this->review_required,
        ];
    }
}
