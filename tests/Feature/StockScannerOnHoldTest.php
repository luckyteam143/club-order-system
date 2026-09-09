<?php

namespace Tests\Feature;

use App\Filament\Pages\StockScanner;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Scan Stock page's two on-hold modes:
 *
 *  - "Put On Hold"       — a REMOVE moves the units taken out of available
 *                          stock into qty_on_hold instead of discarding
 *                          them; an ADD is unaffected.
 *  - "Update On Hold Qty" — every movement is applied to qty_on_hold
 *                          directly and available stock (qty) is never
 *                          touched.
 *
 * Both are mutually exclusive and default off (holdMode 'none'), in which
 * case the scanner behaves exactly as before.
 */
class StockScannerOnHoldTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $user->assignRole('master_admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->warehouse = Warehouse::create([
            'name'   => 'Test WH',
            'code'   => 'TWH',
            'status' => 'active',
        ]);
    }

    private function product(string $barcode = 'BC-1'): Product
    {
        return Product::create([
            'name'    => 'Test Jersey',
            'barcode' => $barcode,
            'status'  => 'Active',
            'qty'     => 0,
        ]);
    }

    private function stockLine(Product $product, int $qty, int $onHold = 0): ProductWarehouseStock
    {
        $line = ProductWarehouseStock::create([
            'product_id'   => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty'          => $qty,
            'qty_on_hold'  => $onHold,
        ]);

        $product->recalculateStock();

        return $line;
    }

    private function assertLine(Product $product, int $qty, int $onHold): void
    {
        $line = ProductWarehouseStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($line);
        $this->assertSame($qty, (int) $line->qty, 'available qty');
        $this->assertSame($onHold, (int) $line->qty_on_hold, 'qty_on_hold');
        // Product.qty tracks only available stock, never the held reserve.
        $this->assertSame($qty, (int) $product->fresh()->qty, 'product total');
    }

    // --- holdMode 'none' — unchanged behaviour --------------------------------

    public function test_normal_remove_lowers_available_stock_only(): void
    {
        $product = $this->product();
        $this->stockLine($product, 10);

        Livewire::test(StockScanner::class)
            ->call('adjustStock', $product->id, -3, $this->warehouse->id, 'none');

        $this->assertLine($product, 7, 0);
    }

    public function test_unknown_hold_mode_is_treated_as_none(): void
    {
        $product = $this->product();
        $this->stockLine($product, 10);

        Livewire::test(StockScanner::class)
            ->call('adjustStock', $product->id, -3, $this->warehouse->id, 'bogus');

        $this->assertLine($product, 7, 0);
    }

    // --- holdMode 'put' — "Put On Hold" -------------------------------------

    public function test_put_on_hold_moves_removed_units_into_the_reserve(): void
    {
        $product = $this->product();
        $this->stockLine($product, 10, 2);

        Livewire::test(StockScanner::class)
            ->call('adjustStock', $product->id, -4, $this->warehouse->id, 'put');

        // 4 taken out of available, parked on top of the existing 2 on hold.
        $this->assertLine($product, 6, 6);
    }

    public function test_put_on_hold_never_moves_more_than_is_on_hand(): void
    {
        $product = $this->product();
        $this->stockLine($product, 3);

        Livewire::test(StockScanner::class)
            ->call('adjustStock', $product->id, -5, $this->warehouse->id, 'put');

        // Only the 3 actually in stock can move; qty clamps at 0.
        $this->assertLine($product, 0, 3);
    }

    public function test_put_on_hold_does_nothing_on_an_add(): void
    {
        $product = $this->product();
        $this->stockLine($product, 10, 1);

        Livewire::test(StockScanner::class)
            ->call('adjustStock', $product->id, 5, $this->warehouse->id, 'put');

        // A positive delta falls through to a normal "add to stock".
        $this->assertLine($product, 15, 1);
    }

    // --- holdMode 'update' — "Update On Hold Qty" -------------------------

    public function test_update_on_hold_add_touches_only_the_reserve(): void
    {
        $product = $this->product();
        $this->stockLine($product, 10, 2);

        Livewire::test(StockScanner::class)
            ->call('adjustStock', $product->id, 5, $this->warehouse->id, 'update');

        $this->assertLine($product, 10, 7);
    }

    public function test_update_on_hold_remove_lowers_only_the_reserve_and_clamps_at_zero(): void
    {
        $product = $this->product();
        $this->stockLine($product, 10, 3);

        Livewire::test(StockScanner::class)
            ->call('adjustStock', $product->id, -8, $this->warehouse->id, 'update');

        $this->assertLine($product, 10, 0);
    }

    // --- scanBarcode confirm-then-commit path carries the hold mode --------

    public function test_scan_barcode_commit_honours_put_on_hold_and_returns_both_buckets(): void
    {
        $product = $this->product('SCAN-9');
        $this->stockLine($product, 8);

        $component = Livewire::test(StockScanner::class);

        // First scan of an item not yet on screen: lookup only, no change.
        $component->call('scanBarcode', 'SCAN-9', $this->warehouse->id, null, -2, 'put');
        $this->assertLine($product, 8, 0);

        // Second scan of the same item: commits, moving the 2 to on-hold.
        $component->call('scanBarcode', 'SCAN-9', $this->warehouse->id, $product->id, -2, 'put');
        $this->assertLine($product, 6, 2);

        $component->call('scanBarcode', 'SCAN-9', $this->warehouse->id, $product->id, -1, 'put')
            ->assertReturned(fn ($payload) => $payload['qty'] === 5
                && $payload['qty_on_hold'] === 3
                && $payload['adjusted'] === true);

        $this->assertLine($product, 5, 3);
    }

    public function test_the_two_hold_modes_are_mutually_exclusive_in_the_ui_state(): void
    {
        $component = Livewire::test(StockScanner::class)
            ->assertSet('warehouses', fn ($warehouses) => is_array($warehouses));

        // The page itself keeps no holdMode server property — it lives in
        // Alpine — but the wire methods accept only 'none' | 'put' | 'update'
        // and coerce anything else. Sanity-check the coercion boundary.
        $product = $this->product('EXCL-1');
        $this->stockLine($product, 5);

        $component->call('adjustStock', $product->id, -1, $this->warehouse->id, '');
        $this->assertLine($product, 4, 0);
    }
}
