<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `products.barcode` already carries a unique index from the create
     * migration; this adds the matching one for `default_sku`. MySQL allows
     * multiple NULLs in a unique index, so future products without a
     * default SKU still insert fine.
     */
    public function up(): void
    {
        // Fresh installs already get this index from the create_products_table
        // migration; only existing databases need it added here.
        if (Schema::hasIndex('products', 'products_default_sku_unique')) {
            return;
        }

        $duplicates = DB::table('products')
            ->select('default_sku')
            ->whereNotNull('default_sku')
            ->where('default_sku', '!=', '')
            ->groupBy('default_sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('default_sku');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add a unique index on products.default_sku — resolve these duplicated values first: '
                . $duplicates->take(20)->implode(', ')
                . ($duplicates->count() > 20 ? ' …' : '')
            );
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('default_sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['default_sku']);
        });
    }
};
