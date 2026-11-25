<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('guardians', 'sms_consent')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->dropColumn('sms_consent');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('guardians', 'sms_consent')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->boolean('sms_consent')->default(false)->after('address');
            });
        }
    }
};
