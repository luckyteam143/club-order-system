<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flags a logo upload as a vector source file (EPS/AI/PDF/SVG) that
     * needs converting to a usable raster image before it can actually be
     * used — set by whoever uploads it, cleared by an admin once handled.
     */
    public function up(): void
    {
        Schema::table('sponsor_logos', function (Blueprint $table) {
            $table->boolean('conversion_requested')->default(false)->after('file');
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_logos', function (Blueprint $table) {
            $table->dropColumn('conversion_requested');
        });
    }
};
