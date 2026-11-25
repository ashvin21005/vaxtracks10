<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->string('gateway_message_id')->nullable()->after('failure_reason');
            $table->string('gateway_queue_id')->nullable()->after('gateway_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->dropColumn(['gateway_message_id', 'gateway_queue_id']);
        });
    }
};
