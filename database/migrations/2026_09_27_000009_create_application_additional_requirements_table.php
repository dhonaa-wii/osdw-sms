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
        // A row appears the first time the applicant uploads or staff record
        // a physical copy; "no row" simply means nothing handed in yet.
        Schema::create('application_additional_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            // Named explicitly — the inferred constraint name overruns MySQL's 64-character limit.
            $table->unsignedBigInteger('offering_additional_requirement_id');
            $table->foreign('offering_additional_requirement_id', 'app_additional_requirements_requirement_foreign')
                ->references('id')
                ->on('offering_additional_requirements')
                ->cascadeOnDelete();
            $table->string('file_path', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('physical_received_at')->nullable();
            $table->foreignId('physical_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['application_id', 'offering_additional_requirement_id'],
                'app_additional_requirements_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_additional_requirements');
    }
};
