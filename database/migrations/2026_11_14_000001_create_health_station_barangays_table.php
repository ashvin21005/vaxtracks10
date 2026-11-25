<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_station_barangay', function (Blueprint $table) {
            $table->foreignId('health_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->primary(['health_station_id', 'barangay_id']);
        });

        foreach (DB::table('health_stations')->get(['id', 'barangay']) as $station) {
            $barangayId = DB::table('barangays')->where('official_name', $station->barangay)->value('id');
            if ($barangayId) {
                DB::table('health_station_barangay')->insertOrIgnore([
                    'health_station_id' => $station->id,
                    'barangay_id' => $barangayId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_station_barangay');
    }
};