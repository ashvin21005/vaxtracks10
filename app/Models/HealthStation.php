<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HealthStation extends Model
{
    protected $fillable = ['name', 'barangay', 'address', 'status'];

    public function barangays(): BelongsToMany
    {
        return $this->belongsToMany(Barangay::class, 'health_station_barangay', 'health_station_id', 'barangay_id')
            ->orderBy('official_name');
    }

    public function getBarangayLabelAttribute(): string
    {
        $names = $this->relationLoaded('barangays')
            ? $this->barangays->pluck('official_name')
            : $this->barangays()->pluck('official_name');

        return $names->isNotEmpty() ? $names->implode(', ') : $this->barangay;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function children()
    {
        return $this->hasMany(Child::class);
    }

    public function vaccineInventory()
    {
        return $this->hasMany(VaccineInventory::class);
    }
}
