<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        $barangays = [

            // ==========================================
            // LIBERTAD / DOWNTOWN
            // ==========================================
            ['official_name' => 'Barangay 1', 'name' => 'Libertad'],
            ['official_name' => 'Barangay 4', 'name' => 'Libertad'],
            ['official_name' => 'Barangay 5', 'name' => 'T. Claudio St.'],

            // ==========================================
            // NULA-TULA
            // ==========================================
            ['official_name' => 'Barangay 3', 'name' => 'Nula-tula'],
            ['official_name' => 'Barangay 3-A', 'name' => 'Nula-tula'],

            // ==========================================
            // EL REPOSO / MAGALLANES
            // ==========================================
            ['official_name' => 'Barangay 55', 'name' => 'El Reposo'],
            ['official_name' => 'Barangay 56', 'name' => 'El Reposo'],
            ['official_name' => 'Barangay 54', 'name' => 'Magallanes'],

            // ==========================================
            // SAGKAHAN
            // ==========================================
            ['official_name' => 'Barangay 59', 'name' => 'Sagkahan Picas'],
            ['official_name' => 'Barangay 62-B', 'name' => 'Sagkahan Picas'],
            ['official_name' => 'Barangay 62', 'name' => 'Sagkahan Bliss'],
            ['official_name' => 'Barangay 62-A', 'name' => 'Sagkahan Saging'],
            ['official_name' => 'Barangay 63', 'name' => 'Sagkahan Mangga'],

            // ==========================================
            // ANIBONG
            // ==========================================
            ['official_name' => 'Barangay 66', 'name' => 'Anibong'],
            ['official_name' => 'Barangay 67', 'name' => 'Anibong'],
            ['official_name' => 'Barangay 68', 'name' => 'Anibong'],

            // ==========================================
            // NAGA-NAGA
            // ==========================================
            ['official_name' => 'Barangay 71', 'name' => 'Naga-naga'],

            // ==========================================
            // PHHC
            // ==========================================
            ['official_name' => 'Barangay 72', 'name' => 'PHHC Seaside'],
            ['official_name' => 'Barangay 73', 'name' => 'PHHC Mountainside'],

            // ==========================================
            // LOWER NULA-TULA
            // ==========================================
            ['official_name' => 'Barangay 74', 'name' => 'Lower Nula-tula'],

            // ==========================================
            // FATIMA VILLAGE
            // ==========================================
            ['official_name' => 'Barangay 75', 'name' => 'Fatima Village'],
            ['official_name' => 'Barangay 76', 'name' => 'Fatima Village'],
            ['official_name' => 'Barangay 77', 'name' => 'Fatima Village'],

            // ==========================================
            // MARASBARAS
            // ==========================================
            ['official_name' => 'Barangay 78', 'name' => 'Marasbaras'],
            ['official_name' => 'Barangay 79', 'name' => 'Marasbaras'],
            ['official_name' => 'Barangay 80', 'name' => 'Marasbaras'],
            ['official_name' => 'Barangay 81', 'name' => 'Marasbaras'],
            ['official_name' => 'Barangay 82', 'name' => 'Marasbaras'],

            // ==========================================
            // SAN JOSE
            // ==========================================
            ['official_name' => 'Barangay 83', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 83-A', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 83-C', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 84', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 85', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 86', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 87', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 88', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 89', 'name' => 'San Jose'],
            ['official_name' => 'Barangay 90', 'name' => 'San Jose'],

            // ==========================================
            // BAYBAY
            // ==========================================
            ['official_name' => 'Barangay 89-A', 'name' => 'Baybay'],

            // ==========================================
            // NORTHERN TACLOBAN
            // ==========================================
            ['official_name' => 'Barangay 91', 'name' => 'Abucay'],
            ['official_name' => 'Barangay 92', 'name' => 'Apitong'],
            ['official_name' => 'Barangay 93', 'name' => 'Bagacay'],
            ['official_name' => 'Barangay 94', 'name' => 'Tigbao'],
            ['official_name' => 'Barangay 94-A', 'name' => 'Basper'],

            // ==========================================
            // CAIBAAN / CALANIPAWAN
            // ==========================================
            ['official_name' => 'Barangay 95', 'name' => 'Caibaan'],
            ['official_name' => 'Barangay 95-A', 'name' => 'Caibaan'],
            ['official_name' => 'Barangay 96', 'name' => 'Calanipawan'],
            ['official_name' => 'Barangay 97', 'name' => 'Cabalawan'],
            ['official_name' => 'Barangay 98', 'name' => 'Camansinay'],
            ['official_name' => 'Barangay 99', 'name' => 'Diit'],

            // ==========================================
            // KAWAYAN / PALANOG
            // ==========================================
            ['official_name' => 'Barangay 100', 'name' => 'San Roque, Scandinavian Subd.'],
            ['official_name' => 'Barangay 101', 'name' => 'New Kawayan'],
            ['official_name' => 'Barangay 102', 'name' => 'Old Kawayan'],
            ['official_name' => 'Barangay 103', 'name' => 'Palanog'],
            ['official_name' => 'Barangay 37-A', 'name' => 'Palanog'],
            ['official_name' => 'Barangay 12', 'name' => 'GE Palanog'],
            ['official_name' => 'Barangay 103-A', 'name' => 'San Paglaum'],

            // ==========================================
            // SOUTHERN / EASTERN TACLOBAN
            // ==========================================
            ['official_name' => 'Barangay 104', 'name' => 'Salvacion'],
            ['official_name' => 'Barangay 105', 'name' => 'Suhi'],
            ['official_name' => 'Barangay 106', 'name' => 'Santo Niño'],
            ['official_name' => 'Barangay 107', 'name' => 'Sta. Elena'],
            ['official_name' => 'Barangay 108', 'name' => 'Tagapuro'],

            // ==========================================
            // V&G SUBDIVISION
            // ==========================================
            ['official_name' => 'Barangay 109', 'name' => 'V&G Subdivision'],
            ['official_name' => 'Barangay 109-A', 'name' => 'V&G Subdivision'],

            // ==========================================
            // UTAP
            // ==========================================
            ['official_name' => 'Barangay 110', 'name' => 'Utap'],
        ];

        foreach ($barangays as $barangay) {
            DB::table('barangays')->updateOrInsert(
                [
                    'official_name' => $barangay['official_name'],
                ],
                [
                    'name' => $barangay['name'],
                    'city' => 'Tacloban City',
                    'province' => 'Leyte',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
