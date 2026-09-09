<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporarily lifts the FK added in 2026_08_31_130000_add_parent_sku_foreign_key_to_products_table
 * so parent_sku can be bulk-updated via product import without every row's
 * new value having to already exist as a default_sku (e.g. mid-import,
 * before the parent row itself has been updated). Re-add later by running
 * that migration's up() logic again once the parent_sku rewrite is done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_parent_sku_foreign');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('parent_sku')
                ->references('default_sku')
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
