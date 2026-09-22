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
        Schema::create('program_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->integer('total_slots');
            $table->integer('max_applications')->nullable();
            $table->string('college_scope', 20);
            $table->string('allocation_mode', 20);
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->boolean('results_published')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_offerings');
    }
};
