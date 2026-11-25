<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaccineInventory extends Model
{
    protected $table = 'vaccine_inventory';

    protected $fillable = ['health_station_id', 'vaccine_id', 'vaccine', 'quantity', 'reorder_level'];

    public function healthStation()
    {
        return $this->belongsTo(HealthStation::class);
    }

    public function vaccineDefinition()
    {
        return $this->belongsTo(Vaccine::class, 'vaccine_id');
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->quantity === 0) {
            return 'OUT OF STOCK';
        }

        return $this->quantity <= $this->reorder_level ? 'LOW STOCK' : 'IN STOCK';
    }
}
