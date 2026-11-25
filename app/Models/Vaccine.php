<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Vaccine extends Model
{
    protected $fillable = ['name', 'description', 'is_active', 'epi_code'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function updateCatalog(array $attributes): void
    {
        DB::transaction(function () use ($attributes) {
            $oldName = $this->name;
            $this->update($attributes);

            if ($this->name === $oldName) {
                return;
            }

            foreach (['immunization_records', 'vaccine_inventory'] as $table) {
                DB::table($table)
                    ->where(fn ($query) => $query->where('vaccine_id', $this->id)->orWhere('vaccine', $oldName))
                    ->update(['vaccine' => $this->name]);
            }

            DB::table('vaccination_schedules')
                ->where('vaccine', $oldName)
                ->update(['vaccine' => $this->name]);
        });
    }

    public function immunizationRecords()
    {
        return $this->hasMany(ImmunizationRecord::class);
    }

    public function inventory()
    {
        return $this->hasMany(VaccineInventory::class);
    }
}
