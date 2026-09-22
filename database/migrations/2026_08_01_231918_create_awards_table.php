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
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->foreignId('awarded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('awarded_at');
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->string('acceptance_status', 20);
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('acceptance_deadline');
            $table->dateTime('compliance_deadline')->nullable();
            $table->string('compliance_status', 20);
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
