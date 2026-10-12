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
        Schema::dropIfExists('application_additional_requirements');
        Schema::dropIfExists('offering_additional_requirements');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('offering_additional_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_offering_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('submission_mode', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('application_additional_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('offering_additional_requirement_id');
            $table->foreign('offering_additional_requirement_id', 'application_additional_requirements_requirement_foreign')
                ->references('id')
                ->on('offering_additional_requirements')
                ->cascadeOnDelete();
            $table->dateTime('received_at');
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
};
