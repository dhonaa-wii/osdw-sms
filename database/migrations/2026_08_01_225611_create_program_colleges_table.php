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
        Schema::create('program_colleges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('college_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['scholarship_program_id', 'college_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_colleges');
    }
};
