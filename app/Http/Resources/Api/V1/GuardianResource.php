<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'relationship' => $this->relationship,
            'contact_number' => $this->contact_number,
            'alternative_contact_number' => $this->alternative_contact_number,
            'occupation' => $this->occupation,
            'address' => $this->address,
        ];
    }
}
