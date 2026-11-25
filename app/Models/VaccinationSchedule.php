<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class VaccinationSchedule extends Model
{
    protected $fillable = [
        'child_id', 'vaccine_id', 'vaccine', 'dose_number', 'scheduled_date',
        'due_date', 'generated_by_protocol', 'review_required', 'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'due_date' => 'date',
            'generated_by_protocol' => 'boolean',
            'review_required' => 'boolean',
        ];
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function vaccineDefinition()
    {
        return $this->belongsTo(Vaccine::class, 'vaccine_id');
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'REVIEW') {
            return 'FOR REVIEW';
        }

        if (in_array($this->status, ['COMPLETED', 'INVALID'], true)) {
            return $this->status;
        }

        if ($this->status === 'MISSED') {
            return 'OVERDUE';
        }

        $dueDate = $this->due_date ?? $this->scheduled_date;
        $epiCode = $this->vaccineDefinition?->epi_code;
        $overdueDays = config("immunization.epi.{$epiCode}.overdue_days", 0);

        if (Carbon::parse($dueDate)->gt(today())) {
            return 'PENDING';
        }

        if (Carbon::parse($dueDate)->addDays($overdueDays)->gte(today())) {
            return 'DUE';
        }

        return 'OVERDUE';
    }
}
