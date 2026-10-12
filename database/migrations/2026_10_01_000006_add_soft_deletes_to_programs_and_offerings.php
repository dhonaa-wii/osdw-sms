<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deleted scholarship program or offering stays restorable from
     * Settings → Deleted items for DeletionService::RETENTION_DAYS, then is
     * removed for good by `osdw:purge-deleted`.
     */
    public function up(): void
    {
        Schema::table('scholarship_programs', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('scholarship_programs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
