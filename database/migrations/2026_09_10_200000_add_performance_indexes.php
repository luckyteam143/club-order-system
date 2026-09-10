<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the large / hot-path tables, from a static pass
 * over the Filament resource queries (sorts, filters, scoping) plus the
 * order-grid catalog build. Secondary-index adds on InnoDB (MySQL 8) run
 * INPLACE / LOCK=NONE, and these tables are small enough (<65k rows) that
 * each completes in well under a second.
 *
 *   products (~30k) ── productsCatalogForGrid() does
 *       `WHERE parent_sku IS NULL AND status = 'Active' ORDER BY name`; with
 *       parent_sku leading, both predicates resolve as equality and `name`
 *       comes out of the index already ordered (no filesort). (status, name)
 *       separately covers the Stock / Product list pages (status filter +
 *       default sort by name).
 *   attribute_product (~62k) ── the GROUP_CONCAT sizes join reads
 *       attribute_id keyed by product_id; (product_id, attribute_id) makes
 *       it a covering scan.
 *   activity_log (grows forever) ── ActivityLogResource default-sorts by
 *       created_at desc and filters on a created_at date range.
 *   orders ── club-user list scoping (club_id / created_by) + default sort
 *       created_at desc; status for the filter and the "submitted" count.
 *
 * Note: on MySQL 8 adding a composite whose first column is a foreign-key
 * column can make the single-column FK index redundant and it gets dropped
 * automatically. down() recreates those FK indexes first, otherwise the
 * composite can't be dropped (errno 1553 "needed in a foreign key
 * constraint").
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: array<int, string>}> */
    private array $indexes = [
        'products_parent_sku_status_name_index' => ['products', ['parent_sku', 'status', 'name']],
        'products_status_name_index' => ['products', ['status', 'name']],
        'attribute_product_product_id_attribute_id_index' => ['attribute_product', ['product_id', 'attribute_id']],
        'activity_log_created_at_index' => ['activity_log', ['created_at']],
        'orders_club_id_created_at_index' => ['orders', ['club_id', 'created_at']],
        'orders_created_by_created_at_index' => ['orders', ['created_by', 'created_at']],
        'orders_status_index' => ['orders', ['status']],
    ];

    /**
     * FK-backing single-column indexes to restore in down() before the
     * composites above are dropped. name => [table, column].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $fkIndexes = [
        'products_parent_sku_foreign' => ['products', 'parent_sku'],
        'attribute_product_product_id_foreign' => ['attribute_product', 'product_id'],
        'orders_club_id_foreign' => ['orders', 'club_id'],
        'orders_created_by_foreign' => ['orders', 'created_by'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => [$table, $columns]) {
            if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->fkIndexes as $name => [$table, $column]) {
            if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($column, $name));
            }
        }

        foreach ($this->indexes as $name => [$table, $columns]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
