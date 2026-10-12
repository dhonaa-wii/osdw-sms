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
        Schema::dropIfExists('application_interviews');

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn(['interview_published', 'interview_published_at']);
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dateTime('interview_at')->nullable()->after('has_interview');
            $table->string('interview_venue', 255)->nullable()->after('interview_at');
            $table->text('interview_instructions')->nullable()->after('interview_venue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn(['interview_at', 'interview_venue', 'interview_instructions']);
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->boolean('interview_published')->default(false)->after('has_interview');
            $table->dateTime('interview_published_at')->nullable()->after('interview_published');
        });

        Schema::create('application_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('venue', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
