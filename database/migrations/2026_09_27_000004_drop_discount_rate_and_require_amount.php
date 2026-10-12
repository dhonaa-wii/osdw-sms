<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tuition discount is gone — every offering carries a cash amount.
        // Backfill any offering that was previously discount-only before the
        // column is dropped and amount becomes required.
        DB::table('program_offerings')->whereNull('amount')->update(['amount' => 0]);
        DB::table('awards')->whereNull('amount')->update(['amount' => 0]);

        Schema::table('program_offerings', function (Blueprint $table) {
            $table->dropColumn('discount_rate');
            $table->decimal('amount', 10, 2)->nullable(false)->change();
        });

        Schema::table('awards', function (Blueprint $table) {
            $table->dropColumn('discount_rate');
            $table->decimal('amount', 10, 2)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_offerings', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->change();
            $table->decimal('discount_rate', 5, 2)->nullable();
        });

        Schema::table('awards', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->change();
            $table->decimal('discount_rate', 5, 2)->nullable();
        });
    }
};
