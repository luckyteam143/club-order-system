<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns `products.parent_sku` into a real foreign key pointing at the
     * parent product's `products.default_sku` (which already carries a
     * unique index from 2026_08_27_130000). A size variant references its
     * parent this way; a parent/master product leaves `parent_sku` NULL.
     *
     *  - onUpdate cascade  : renaming a parent's Default SKU follows through
     *                        to its variants, keeping the group intact.
     *  - onDelete restrict : a parent that still has variants can't be
     *                        deleted until those variants are reassigned or
     *                        removed.
     *
     * Side effect of the FK: MySQL adds an index on `parent_sku`, which also
     * speeds up the many `whereNull('parent_sku')` / `where('parent_sku', …)`
     * lookups across the app.
     */
    public function up(): void
    {
        if ($this->foreignKeyExists('products_parent_sku_foreign')) {
            return;
        }

        // Clear any parent_sku that doesn't resolve to an existing
        // default_sku (the parent row is missing) — MySQL rejects the
        // constraint otherwise. Affected ids are printed so the broken
        // links can be rebuilt by hand afterwards.
        $orphanIds = DB::table('products as child')
            ->whereNotNull('child.parent_sku')
            ->where('child.parent_sku', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('products as parent')
                    ->whereColumn('parent.default_sku', 'child.parent_sku');
            })
            ->pluck('child.id');

        if ($orphanIds->isNotEmpty()) {
            DB::table('products')->whereIn('id', $orphanIds)->update(['parent_sku' => null]);

            echo PHP_EOL . '  Cleared parent_sku on ' . $orphanIds->count()
                . ' product row(s) with no matching parent — ids: '
                . $orphanIds->implode(', ') . PHP_EOL;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('parent_sku')
                ->references('default_sku')
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_parent_sku_foreign');
        });
    }

    private function foreignKeyExists(string $name): bool
    {
        return ! empty(DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = ?",
            [$name]
        ));
    }
};
