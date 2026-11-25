<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INITIAL_VACCINES = ['BCG', 'Hepatitis B', 'Pentavalent', 'OPV', 'IPV', 'PCV', 'MMR'];

    public function up(): void
    {
        Schema::create('vaccines', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $names = collect(self::INITIAL_VACCINES);
        foreach (['immunization_records', 'vaccine_inventory'] as $table) {
            $names = $names->merge(DB::table($table)->distinct()->pluck('vaccine'));
        }

        $now = now();
        foreach ($names->filter(fn ($name) => is_string($name) && $name !== '')->unique() as $name) {
            DB::table('vaccines')->insertOrIgnore([
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['immunization_records', 'vaccine_inventory'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('vaccine_id')->nullable()->constrained('vaccines')->restrictOnDelete();
            });

            foreach (DB::table($table)->select('id', 'vaccine')->get() as $row) {
                $vaccineId = DB::table('vaccines')->where('name', $row->vaccine)->value('id');
                if ($vaccineId) {
                    DB::table($table)->where('id', $row->id)->update(['vaccine_id' => $vaccineId]);
                }
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('vaccine_id');
            });
        }

        Schema::table('immunization_records', function (Blueprint $table) {
            $table->unique(['child_id', 'vaccine_id', 'dose_number']);
        });

        Schema::table('vaccine_inventory', function (Blueprint $table) {
            $table->unique(['health_station_id', 'vaccine_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vaccine_inventory', function (Blueprint $table) {
            $table->dropUnique(['health_station_id', 'vaccine_id']);
        });
        Schema::table('immunization_records', function (Blueprint $table) {
            $table->dropUnique(['child_id', 'vaccine_id', 'dose_number']);
        });

        foreach (['vaccine_inventory', 'immunization_records'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['vaccine_id']);
                $blueprint->dropConstrainedForeignId('vaccine_id');
            });
        }

        Schema::dropIfExists('vaccines');
    }
};
