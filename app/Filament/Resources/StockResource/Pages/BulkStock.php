<?php

namespace App\Filament\Resources\StockResource\Pages;

use App\Filament\Resources\StockResource;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class BulkStock extends Page
{
    protected static string $resource = StockResource::class;

    protected static string $view = 'filament.resources.stock-resource.pages.bulk-stock';

    protected static ?string $title = 'Bulk Add / Edit Stock';

    // Plain Livewire property (not a Filament Form) — the grid is a raw
    // Alpine-managed JSON blob synced via $wire.$set(), same mechanism as
    // the Order page's grid, just without the Form wrapper since this page
    // isn't editing a single record. Shape: { [productId]: { cells: {
    // [warehouseId]: { qty, on_hold, id } } } } — keyed by product id (not
    // a plain array) so edits survive the visible list changing as the
    // search term changes.
    public string $gridState = '{}';

    public array $warehousesForGrid = [];

    // Prefills the grid's search box when arriving via a "?q=" link (e.g.
    // the Stock listing's per-row Edit action), so that link lands directly
    // on the matching row instead of an empty search.
    public string $initialSearch = '';

    public function mount(): void
    {
        $this->initialSearch = (string) request()->query('q', '');

        // Unlike the item pickers elsewhere, this page's catalog includes
        // every size variant (~30k rows) — too large to embed and filter
        // client-side without the page itself becoming slow to load and
        // search. Products are searched on demand instead (searchProducts
        // below); only the small warehouse list is loaded up front.
        $this->warehousesForGrid = Warehouse::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'name' => $warehouse->name])
            ->values()
            ->all();
    }

    /**
     * Server-side search backing the grid's search box — deliberately
     * includes both parent and child (size-variant) products, since stock
     * is tracked per sellable SKU/size, not per parent.
     *
     * @return array<int, array{id: int, name: string, barcode: ?string, size: ?string}>
     */
    public function searchProducts(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        return Product::query()
            ->select(['id', 'name', 'barcode', 'size'])
            ->where('status', 'Active')
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%")
                    ->orWhere('size', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (Product $product) => [
                'id'      => $product->id,
                'name'    => $product->name,
                'barcode' => $product->barcode,
                'size'    => $product->size,
            ])
            ->values()
            ->all();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('manage_stock') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Stock')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('saveStock'),
        ];
    }

    /**
     * Called from the grid whenever new products become visible for the
     * first time (a whole batch of search results at once, not one call
     * per row), so items that already have stock show their current
     * quantities to edit rather than blank fields. Existing stock isn't
     * preloaded for the whole catalog up front — only fetched for products
     * the search has actually surfaced.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array<int, array{id: int, warehouse_id: int, qty: int, qty_on_hold: int}>> keyed by product id
     */
    public function getExistingStockBatch(array $productIds): array
    {
        return ProductWarehouseStock::whereIn('product_id', $productIds)
            ->get(['id', 'product_id', 'warehouse_id', 'qty', 'qty_on_hold'])
            ->groupBy('product_id')
            ->map(fn ($stocks) => $stocks->map(fn (ProductWarehouseStock $stock) => [
                'id'           => $stock->id,
                'warehouse_id' => $stock->warehouse_id,
                'qty'          => $stock->qty,
                'qty_on_hold'  => $stock->qty_on_hold,
            ])->values()->all())
            ->all();
    }

    public function saveStock(): void
    {
        $edits = json_decode($this->gridState, true) ?: [];

        $touchedProductIds = [];

        DB::transaction(function () use ($edits, &$touchedProductIds) {
            foreach ($edits as $productId => $row) {
                if (blank($productId)) {
                    continue;
                }

                foreach (($row['cells'] ?? []) as $warehouseId => $cell) {
                    // qty and on_hold are each "blank = untouched" on their
                    // own — an explicit 0 in either is still saved.
                    $qty = $cell['qty'] ?? null;

                    if ($qty !== null && $qty !== '') {
                        ProductWarehouseStock::applyQty((int) $productId, (int) $warehouseId, (int) $qty);
                    }

                    $onHold = $cell['on_hold'] ?? null;

                    if ($onHold !== null && $onHold !== '') {
                        ProductWarehouseStock::applyOnHoldQty((int) $productId, (int) $warehouseId, (int) $onHold);
                    }
                }

                $touchedProductIds[(int) $productId] = true;
            }
        });

        Product::whereIn('id', array_keys($touchedProductIds))
            ->get()
            ->each(fn (Product $product) => $product->recalculateStock());

        Notification::make()
            ->title(count($touchedProductIds) . ' product(s) updated')
            ->success()
            ->send();
    }
}
