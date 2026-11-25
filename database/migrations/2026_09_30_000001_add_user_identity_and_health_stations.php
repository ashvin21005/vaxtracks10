<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('barangay')->index();
            $table->string('address')->nullable();
            $table->string('status')->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('BHW')->change();
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('account_status')->default('ACTIVE')->after('role');
            $table->foreignId('health_station_id')->nullable()->after('barangay')->constrained()->nullOnDelete();
        });

        DB::table('users')->where('role', 'CHO')->update(['role' => 'CHO_ADMIN']);

        foreach (DB::table('users')->whereNull('username')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = Str::slug(Str::before($user->email, '@'), '.');
            $username = $base ?: 'user';
            $suffix = 1;

            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $base.$suffix++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
        });

        Schema::table('children', function (Blueprint $table) {
            $table->foreignId('health_station_id')->nullable()->after('guardian_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'CHO_ADMIN')->update(['role' => 'CHO']);

        Schema::table('children', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_station_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_station_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'account_status']);
        });

        Schema::dropIfExists('health_stations');
    }
};
