<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VaccineSeeder extends Seeder
{
    private const VACCINES = [
        'BCG' => 'BCG',
        'Hepatitis B' => 'HEPB_BIRTH',
        'Pentavalent' => 'PENTAVALENT',
        'OPV' => 'OPV',
        'IPV' => 'IPV',
        'PCV' => 'PCV',
        'MMR' => 'MMR',
    ];

    public function run(): void
    {
        foreach (self::VACCINES as $name => $epiCode) {
            DB::table('vaccines')->insertOrIgnore([
                'name' => $name,
                'epi_code' => $epiCode,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
