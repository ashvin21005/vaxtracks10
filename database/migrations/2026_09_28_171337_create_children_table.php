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
       Schema::create('children', function (Blueprint $table) {
              $table->id();
              $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
              $table->string('first_name');
              $table->string('middle_name')->nullable();
              $table->string('last_name');
              $table->enum('sex', ['Male', 'Female']);
              $table->date('date_of_birth');
              $table->string('place_of_birth')->nullable();
              $table->decimal('birth_weight', 4, 2)->nullable();
              $table->string('barangay');
              $table->string('address')->nullable();
              $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
