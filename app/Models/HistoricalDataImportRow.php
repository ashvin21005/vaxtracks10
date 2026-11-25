<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalDataImportRow extends Model
{
    protected $fillable = ['batch_id', 'row_number', 'raw_data', 'validation_errors', 'status'];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'validation_errors' => 'array',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(HistoricalDataImportBatch::class, 'batch_id');
    }

    public function indicator()
    {
        return $this->hasOne(HistoricalIndicator::class, 'import_row_id');
    }
}
