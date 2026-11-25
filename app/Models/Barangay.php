<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $fillable = ['official_name', 'name', 'city', 'province'];

    public function historicalIndicators()
    {
        return $this->hasMany(HistoricalIndicator::class);
    }
}
