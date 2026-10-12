<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff record when an award's fund was released to the grantee, once every
 * disbursement requirement is complied. The application stays awarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dateTime('disbursed_at')->nullable()->after('awarded_at');
            $table->foreignId('disbursed_by')->nullable()->after('disbursed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursed_by');
            $table->dropColumn('disbursed_at');
        });
    }
};
