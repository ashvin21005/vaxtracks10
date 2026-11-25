<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalIndicator extends Model
{
    protected $fillable = [
        'batch_id',
        'import_row_id',
        'period_start',
        'barangay_id',
        'metric_type',
        'vaccine_id',
        'disease_name',
        'value',
        'source_name',
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'value' => 'integer',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(HistoricalDataImportBatch::class, 'batch_id');
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function vaccine()
    {
        return $this->belongsTo(Vaccine::class);
    }
}
