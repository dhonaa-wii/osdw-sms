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
        Schema::create('offering_additional_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_offering_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('submission_mode', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // A row exists only once that applicant has handed the item in, so the
        // receipt table stays empty until staff start ticking. Outstanding is
        // "an offering requirement with no receipt", which needs no backfill
        // when a requirement is added or an applicant reaches the shortlist.
        Schema::create('application_additional_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            // Named explicitly — the inferred constraint name overruns MySQL's 64-character limit.
            $table->unsignedBigInteger('offering_additional_requirement_id');
            $table->foreign('offering_additional_requirement_id', 'application_additional_requirements_requirement_foreign')
                ->references('id')
                ->on('offering_additional_requirements')
                ->cascadeOnDelete();
            $table->dateTime('received_at');
            // Set only when the applicant uploaded the item themselves; a
            // physical-copy receipt is staff ticking the box and carries no file.
            $table->string('file_path', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['application_id', 'offering_additional_requirement_id'],
                'application_additional_requirements_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_additional_requirements');
        Schema::dropIfExists('offering_additional_requirements');
    }
};
