<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The standard requirements: one application form and one set of
     * disbursement requirements the admin keeps for every scholarship. An
     * offering either uses them (a copy, kept in step until applications
     * come in) or sets its own manually.
     */
    public function up(): void
    {
        Schema::create('standard_form_fields', function (Blueprint $table) {
            $table->id();
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

        Schema::create('standard_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('label', 150);
            $table->text('instructions')->nullable();
            $table->string('submission_mode', 20);
            $table->string('accepted_formats', 100)->nullable();
            $table->unsignedInteger('max_size_mb')->nullable();
            $table->string('template_path')->nullable();
            $table->string('template_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->string('requirements_source', 20)->default('manual')->after('accepts_new_applicants');
        });
    }

    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn('requirements_source');
        });

        Schema::dropIfExists('standard_requirements');
        Schema::dropIfExists('standard_form_fields');
    }
};
