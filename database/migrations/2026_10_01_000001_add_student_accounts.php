<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students sign in with an account tied to their email. One account owns one
 * applicant record; tracking no longer goes through a per-application token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A student who signs up with Google has no password until they set one.
            $table->string('password')->nullable()->change();
            $table->string('google_id')->nullable()->unique()->after('email');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dateTime('registration_opened_notified_at')->nullable();
            $table->dateTime('registration_closed_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn(['registration_opened_notified_at', 'registration_closed_notified_at']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('access_token', 64)->default('');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
