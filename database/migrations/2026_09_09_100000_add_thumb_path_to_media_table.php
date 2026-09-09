<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Small (<=240px) rasterised preview generated on upload. The
            // listing loads this instead of the full-size `file` so the
            // Media Library table stays fast. Null for entries whose
            // thumbnail hasn't been generated yet, or SVGs (which fall
            // back to `file`, already tiny).
            $table->string('thumb_path')->nullable()->after('file');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('thumb_path');
        });
    }
};
