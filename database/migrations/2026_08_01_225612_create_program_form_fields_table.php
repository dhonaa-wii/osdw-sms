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
        Schema::create('program_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_program_id')->constrained()->cascadeOnDelete();
            $table->string('field_type', 20);
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->text('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_physical_copy')->default(false);
            $table->string('accepted_formats', 100)->nullable();
            $table->integer('max_size_mb')->nullable();
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_form_fields');
    }
};
