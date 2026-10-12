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
        Schema::create('branding_images', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->binary('data');
            $table->timestamps();
        });

        // Laravel's binary() is a 64 KB BLOB on MySQL; images need LONGBLOB.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE branding_images MODIFY data LONGBLOB NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branding_images');
    }
};
