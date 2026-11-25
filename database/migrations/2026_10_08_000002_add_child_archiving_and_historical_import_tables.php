<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('historical_data_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_name');
            $table->string('source_reference')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->string('status')->default('PROCESSING')->index();
            $table->timestamps();
        });

        Schema::create('historical_data_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('historical_data_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('validation_errors')->nullable();
            $table->string('status')->index();
            $table->timestamps();

            $table->unique(['batch_id', 'row_number']);
        });

        Schema::create('historical_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('historical_data_import_batches')->restrictOnDelete();
            $table->foreignId('import_row_id')->unique()->constrained('historical_data_import_rows')->restrictOnDelete();
            $table->date('period_start')->index();
            $table->foreignId('barangay_id')->constrained('barangays')->restrictOnDelete();
            $table->string('metric_type')->index();
            $table->foreignId('vaccine_id')->nullable()->constrained('vaccines')->restrictOnDelete();
            $table->string('disease_name')->nullable();
            $table->unsignedInteger('value');
            $table->string('source_name');
            $table->string('source_reference')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'barangay_id', 'metric_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_indicators');
        Schema::dropIfExists('historical_data_import_rows');
        Schema::dropIfExists('historical_data_import_batches');

        Schema::table('children', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
