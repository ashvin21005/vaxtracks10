<?php

namespace App\Services;

use App\Models\Child;
use Illuminate\Validation\ValidationException;

class PatientRegistrationService
{
    public function ensureNoUnconfirmedDuplicate(array $data): void
    {
        if (filter_var($data['duplicate_confirmation'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $duplicateExists = Child::withTrashed()
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($data['first_name']))])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($data['last_name']))])
            ->whereDate('date_of_birth', $data['date_of_birth'])
            ->where('sex', $data['sex'])
            ->where('barangay', $data['barangay'])
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'duplicate_confirmation' => 'A child with these details is already registered. Check the existing patient record before confirming this is a different child.',
            ]);
        }
    }
}