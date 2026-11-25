<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EPI_CODES = [
        'BCG' => 'BCG',
        'Hepatitis B' => 'HEPB_BIRTH',
        'Pentavalent' => 'PENTAVALENT',
        'OPV' => 'OPV',
        'IPV' => 'IPV',
        'PCV' => 'PCV',
        'MMR' => 'MMR',
    ];

    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->string('baby_book_id')->nullable()->unique()->after('id');
        });

        foreach (DB::table('children')->orderBy('id')->get(['id']) as $child) {
            DB::table('children')->where('id', $child->id)->update([
                'baby_book_id' => 'BB-'.str_pad((string) $child->id, 6, '0', STR_PAD_LEFT),
            ]);
        }

        Schema::table('vaccines', function (Blueprint $table) {
            $table->string('epi_code')->nullable()->unique()->after('name');
        });

        foreach (self::EPI_CODES as $name => $code) {
            DB::table('vaccines')->where('name', $name)->update(['epi_code' => $code]);
        }

        Schema::table('immunization_records', function (Blueprint $table) {
            $table->date('administered_at')->nullable()->change();
            $table->string('date_precision')->default('DAY');
            $table->unsignedTinyInteger('administered_month')->nullable();
            $table->unsignedSmallInteger('administered_year')->nullable();
            $table->string('record_type')->default('LIVE');
            $table->string('validation_status')->default('VALID');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
        });

        Schema::table('vaccination_schedules', function (Blueprint $table) {
            $table->foreignId('vaccine_id')->nullable()->constrained('vaccines')->nullOnDelete();
            $table->date('due_date')->nullable()->after('scheduled_date');
            $table->boolean('generated_by_protocol')->default(false);
            $table->boolean('review_required')->default(false);
        });

        foreach (DB::table('vaccination_schedules')->select('id', 'vaccine')->get() as $schedule) {
            $vaccineId = DB::table('vaccines')->where('name', $schedule->vaccine)->value('id');
            if ($vaccineId) {
                DB::table('vaccination_schedules')->where('id', $schedule->id)->update([
                    'vaccine_id' => $vaccineId,
                ]);
            }
        }

        DB::table('vaccination_schedules')->whereNull('due_date')->update([
            'due_date' => DB::raw('scheduled_date'),
        ]);
    }

    public function down(): void
    {
        Schema::table('vaccination_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vaccine_id');
            $table->dropColumn(['due_date', 'generated_by_protocol', 'review_required']);
        });

        Schema::table('immunization_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'date_precision',
                'administered_month',
                'administered_year',
                'record_type',
                'validation_status',
                'verified_at',
            ]);
            $table->date('administered_at')->nullable(false)->change();
        });

        Schema::table('vaccines', function (Blueprint $table) {
            $table->dropUnique(['epi_code']);
            $table->dropColumn('epi_code');
        });

        Schema::table('children', function (Blueprint $table) {
            $table->dropUnique(['baby_book_id']);
            $table->dropColumn('baby_book_id');
        });
    }
};
