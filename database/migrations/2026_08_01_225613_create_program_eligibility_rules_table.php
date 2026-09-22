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
        Schema::create('program_eligibility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_program_id')->constrained()->cascadeOnDelete();
            $table->string('field', 20);
            $table->string('operator', 10);
            $table->string('value', 255);
            $table->string('message', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_eligibility_rules');
    }
};
