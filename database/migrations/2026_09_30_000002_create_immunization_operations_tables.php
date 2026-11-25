<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE')->after('health_station_id')->index();
        });

        Schema::create('immunization_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('vaccine');
            $table->unsignedTinyInteger('dose_number');
            $table->date('administered_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('COMPLETED');
            $table->timestamps();
            $table->unique(['child_id', 'vaccine', 'dose_number']);
            $table->index('administered_at');
        });

        Schema::create('vaccination_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('vaccine');
            $table->unsignedTinyInteger('dose_number');
            $table->date('scheduled_date')->index();
            $table->string('status')->default('UPCOMING')->index();
            $table->timestamps();
            $table->unique(['child_id', 'vaccine', 'dose_number']);
        });

        Schema::create('vaccine_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_station_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('vaccine');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->timestamps();
            $table->unique(['health_station_id', 'vaccine']);
        });

        Schema::create('sms_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vaccination_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('reminder_days');
            $table->date('scheduled_date');
            $table->string('status')->default('PENDING')->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['reminder_days', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notifications');
        Schema::dropIfExists('vaccine_inventory');
        Schema::dropIfExists('vaccination_schedules');
        Schema::dropIfExists('immunization_records');

        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
