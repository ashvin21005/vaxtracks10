<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Child extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'guardian_id', 'first_name', 'middle_name', 'last_name', 'sex',
        'date_of_birth', 'time_of_birth', 'place_of_birth', 'place_of_birth_type',
        'place_of_birth_details', 'birth_facility', 'birth_weight', 'purok', 'street',
        'barangay', 'address', 'health_station_id', 'status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    protected static function booted(): void
    {
        static::created(function (Child $child) {
            $child->forceFill([
                'baby_book_id' => 'BB-'.str_pad((string) $child->id, 6, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function healthStation()
    {
        return $this->belongsTo(HealthStation::class);
    }

    public function immunizationRecords()
    {
        return $this->hasMany(ImmunizationRecord::class);
    }

    public function vaccinationSchedules()
    {
        return $this->hasMany(VaccinationSchedule::class);
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]));
    }

    public function getAgeAttribute(): string
    {
        $months = (int) Carbon::parse($this->date_of_birth)->diffInMonths(now());

        return $months < 24 ? $months.' mo' : intdiv($months, 12).' yr';
    }
}
