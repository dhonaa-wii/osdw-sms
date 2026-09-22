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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();
            $table->string('access_token', 64);
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('college_id')->constrained()->restrictOnDelete();
            $table->tinyInteger('year_level');
            $table->decimal('gwa', 4, 2)->nullable();
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->decimal('document_score', 6, 2)->nullable();
            $table->decimal('composite_score', 6, 2)->nullable();
            $table->integer('rank_in_college')->nullable();
            $table->boolean('consent_publish')->default(false);
            $table->string('status', 20);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('screened_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_id', 'program_offering_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
