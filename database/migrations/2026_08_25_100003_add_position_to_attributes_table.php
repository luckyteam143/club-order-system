<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('sku');
        });

        // Nothing was ordering these before (product size lists were
        // whatever order the DB happened to return), so there's no
        // "current" order to preserve as-is — but existing attribute ids
        // already happen to have been created in a sensible small-to-large
        // sequence (XS, S, M, L, XL… then the numeric sizes), so seed
        // position from id as a reasonable starting point. Editable per
        // attribute afterward via the new Position field.
        DB::table('attributes')->update(['position' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
