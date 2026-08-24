<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate vector source upload (EPS/SVG/AI/PDF) alongside the raster
     * `file` — previously a club could upload a vector into `file` itself
     * and flag `conversion_requested`; `file` is now restricted to PNG/JPG
     * only, so a vector source needs its own column.
     */
    public function up(): void
    {
        Schema::table('sponsor_logos', function (Blueprint $table) {
            $table->string('vector_file')->nullable()->after('file');
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_logos', function (Blueprint $table) {
            $table->dropColumn('vector_file');
        });
    }
};
