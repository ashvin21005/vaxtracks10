<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'relationship', 'contact_number',
        'alternative_contact_number', 'occupation', 'address',
    ];

    public function children()
    {
        return $this->hasMany(Child::class);
    }

    public function immunizationRecords()
    {
        return $this->hasManyThrough(ImmunizationRecord::class, Child::class);
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]));
    }
}
