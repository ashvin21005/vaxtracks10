<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalDataImportBatch extends Model
{
    protected $fillable = [
        'source_name',
        'source_reference',
        'imported_by',
        'total_rows',
        'accepted_rows',
        'rejected_rows',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'accepted_rows' => 'integer',
            'rejected_rows' => 'integer',
        ];
    }

    public function rows()
    {
        return $this->hasMany(HistoricalDataImportRow::class, 'batch_id');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function indicators()
    {
        return $this->hasMany(HistoricalIndicator::class, 'batch_id');
    }
}
