<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
     Schema::create('barangays', function (Blueprint $table) {
    $table->id();
    $table->string('official_name');
    $table->string('name');
    $table->string('city')->default('Tacloban City');
    $table->string('province')->default('Leyte');
    $table->timestamps();

    $table->unique('official_name');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};
