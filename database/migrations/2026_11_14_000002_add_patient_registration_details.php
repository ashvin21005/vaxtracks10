<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->time('time_of_birth')->nullable()->after('date_of_birth');
            $table->string('place_of_birth_type')->nullable()->after('place_of_birth');
            $table->string('place_of_birth_details')->nullable()->after('place_of_birth_type');
            $table->string('birth_facility')->nullable()->after('place_of_birth_details');
            $table->string('purok')->nullable()->after('birth_weight');
            $table->string('street')->nullable()->after('purok');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('alternative_contact_number', 20)->nullable()->after('contact_number');
            $table->string('address')->nullable()->after('occupation');
        });

        Schema::table('immunization_records', function (Blueprint $table) {
            $table->string('date_precision')->nullable()->default('DAY')->change();
            $table->string('history_status')->default('ADMINISTERED')->after('record_type');
            $table->string('administering_facility_type')->nullable()->after('history_status');
            $table->string('administering_facility_name')->nullable()->after('administering_facility_type');
            $table->string('verification_source')->nullable()->after('administering_facility_name');
            $table->boolean('guardian_reported')->default(false)->after('verification_source');
            $table->string('vaccine_brand')->nullable()->after('guardian_reported');
            $table->string('manufacturer')->nullable()->after('vaccine_brand');
            $table->string('batch_number')->nullable()->after('manufacturer');
        });
    }

    public function down(): void
    {
        DB::table('immunization_records')->whereNull('date_precision')->update(['date_precision' => 'DAY']);
        Schema::table('immunization_records', function (Blueprint $table) {
            $table->string('date_precision')->nullable(false)->default('DAY')->change();
        });
        Schema::table('immunization_records', function (Blueprint $table) {
            $table->dropColumn([
                'history_status', 'administering_facility_type', 'administering_facility_name',
                'verification_source', 'guardian_reported', 'vaccine_brand', 'manufacturer', 'batch_number',
            ]);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'alternative_contact_number', 'address']);
        });

        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn(['time_of_birth', 'place_of_birth_type', 'place_of_birth_details', 'birth_facility', 'purok', 'street']);
        });
    }
};