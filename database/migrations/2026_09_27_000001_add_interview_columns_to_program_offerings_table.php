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
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->boolean('has_interview')->default(false)->after('results_published_at');
            $table->boolean('interview_published')->default(false)->after('has_interview');
            $table->dateTime('interview_published_at')->nullable()->after('interview_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn(['has_interview', 'interview_published', 'interview_published_at']);
        });
    }
};
