<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImmunizationRecord extends Model
{
    protected $fillable = [
        'child_id', 'vaccine_id', 'vaccine', 'dose_number', 'administered_at',
        'administered_month', 'administered_year', 'date_precision', 'record_type',
        'history_status', 'administering_facility_type', 'administering_facility_name',
        'verification_source', 'guardian_reported', 'vaccine_brand', 'manufacturer', 'batch_number',
        'validation_status', 'verified_by', 'verified_at', 'recorded_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'date',
            'verified_at' => 'datetime',
            'guardian_reported' => 'boolean',
        ];
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function vaccineDefinition()
    {
        return $this->belongsTo(Vaccine::class, 'vaccine_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getAdministrationDateLabelAttribute(): string
    {
        if ($this->date_precision === 'YEAR') {
            return (string) $this->administered_year;
        }

        if ($this->date_precision === 'MONTH') {
            return \Illuminate\Support\Carbon::create((int) $this->administered_year, (int) $this->administered_month, 1)
                ->format('F Y');
        }

        return $this->administered_at?->format('M d, Y') ?? '—';
    }
}
