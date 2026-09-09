<?php

namespace Tests\Feature;

use App\Filament\Resources\StockResource\Pages\BulkStock;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Bulk Add / Edit Stock grid can now edit the per-warehouse on-hold
 * reserve alongside the available qty. Each is independently "blank =
 * untouched".
 */
class BulkStockOnHoldTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $wh;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $user->assignRole('master_admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->wh = Warehouse::create(['name' => 'Bulk WH', 'code' => 'BWH', 'status' => 'active']);
    }

    private function product(string $barcode): Product
    {
        return Product::create([
            'name' => 'Grid Item', 'barcode' => $barcode, 'status' => 'Active', 'qty' => 0,
        ]);
    }

    /** @param array<string,mixed> $cell */
    private function save(Product $product, array $cell): void
    {
        $state = [
            (string) $product->id => [
                'cells' => [
                    (string) $this->wh->id => $cell,
                ],
            ],
        ];

        Livewire::test(BulkStock::class)
            ->set('gridState', json_encode($state))
            ->call('saveStock');
    }

    private function line(Product $product): ?ProductWarehouseStock
    {
        return ProductWarehouseStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->wh->id)
            ->first();
    }

    public function test_saving_an_on_hold_value_writes_the_reserve_and_leaves_qty_alone(): void
    {
        $product = $this->product('GRID-1');
        ProductWarehouseStock::create([
            'product_id' => $product->id, 'warehouse_id' => $this->wh->id, 'qty' => 12, 'qty_on_hold' => 0,
        ]);

        // qty blank (untouched), on_hold set.
        $this->save($product, ['qty' => '', 'on_hold' => '4', 'id' => null]);

        $line = $this->line($product);
        $this->assertSame(12, (int) $line->qty);
        $this->assertSame(4, (int) $line->qty_on_hold);
    }

    public function test_saving_both_qty_and_on_hold_applies_both(): void
    {
        $product = $this->product('GRID-2');

        $this->save($product, ['qty' => '20', 'on_hold' => '5', 'id' => null]);

        $line = $this->line($product);
        $this->assertSame(20, (int) $line->qty);
        $this->assertSame(5, (int) $line->qty_on_hold);
        $this->assertSame(20, (int) $product->fresh()->qty);
    }

    public function test_blank_on_hold_leaves_an_existing_reserve_untouched(): void
    {
        $product = $this->product('GRID-3');
        ProductWarehouseStock::create([
            'product_id' => $product->id, 'warehouse_id' => $this->wh->id, 'qty' => 1, 'qty_on_hold' => 9,
        ]);

        $this->save($product, ['qty' => '3', 'on_hold' => '', 'id' => null]);

        $line = $this->line($product);
        $this->assertSame(3, (int) $line->qty);
        $this->assertSame(9, (int) $line->qty_on_hold);
    }

    public function test_an_explicit_zero_on_hold_clears_the_reserve(): void
    {
        $product = $this->product('GRID-4');
        ProductWarehouseStock::create([
            'product_id' => $product->id, 'warehouse_id' => $this->wh->id, 'qty' => 1, 'qty_on_hold' => 9,
        ]);

        $this->save($product, ['qty' => '', 'on_hold' => '0', 'id' => null]);

        $this->assertSame(0, (int) $this->line($product)->qty_on_hold);
    }

    public function test_existing_stock_batch_includes_the_on_hold_reserve(): void
    {
        $product = $this->product('GRID-5');
        ProductWarehouseStock::create([
            'product_id' => $product->id, 'warehouse_id' => $this->wh->id, 'qty' => 7, 'qty_on_hold' => 2,
        ]);

        Livewire::test(BulkStock::class)
            ->call('getExistingStockBatch', [$product->id])
            ->assertReturned(fn ($rows) => $rows[$product->id][0]['qty'] === 7
                && $rows[$product->id][0]['qty_on_hold'] === 2);
    }
}
