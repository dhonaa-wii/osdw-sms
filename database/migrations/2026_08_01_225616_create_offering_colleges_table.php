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
        Schema::create('offering_colleges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('college_id')->constrained()->restrictOnDelete();
            $table->integer('slots')->nullable();
            $table->timestamps();

            $table->unique(['program_offering_id', 'college_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offering_colleges');
    }
};
