<?php

namespace App\Filament\Resources\StockResource\Pages;

use App\Exports\StockExport;
use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Pages\StockScanner;
use App\Filament\Resources\StockResource;
use App\Imports\StockImport;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListStock extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = StockResource::class;

    public function getFooter(): View
    {
        return view('filament.resources.stock-resource.pages.list-stock-footer');
    }

    /**
     * Batch-commits the warehouse quantity cells touched on the listing —
     * called only from the footer's explicit "Save Changes" button, never
     * on keystroke.
     *
     * @param  array<int|string, array<string, mixed>>  $edits  productId => ['warehouse_{id}' => qty]
     */
    public function saveInlineEdits(array $edits): void
    {
        $touchedProductIds = [];

        foreach ($edits as $productId => $fields) {
            foreach ($fields as $field => $value) {
                if (! str_starts_with($field, 'warehouse_')) {
                    continue;
                }

                $warehouseId = (int) str_replace('warehouse_', '', $field);

                ProductWarehouseStock::applyQty((int) $productId, $warehouseId, (int) $value);
            }

            $touchedProductIds[] = (int) $productId;
        }

        Product::whereIn('id', $touchedProductIds)->get()->each(fn (Product $product) => $product->recalculateStock());

        Notification::make()
            ->title(count($touchedProductIds).' product(s) updated')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV File')
                        ->disk('local')
                        ->directory('imports/stock')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: Product ID (optional if Barcode given), Barcode, then one Qty column per warehouse (Product Name / Size / Total Look / On Hold (all) / Total are reference only). Matches the Export Excel layout — omitted warehouse columns are left untouched.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    // See ListProducts::import — same 30s/128M FPM limits apply.
                    set_time_limit(600);
                    ini_set('memory_limit', '512M');

                    try {
                        $import = new StockImport;
                        Excel::import($import, $path);
                    } catch (\Throwable $e) {
                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Storage::disk('local')->delete($data['file']);

                    Notification::make()
                        ->title('Stock imported successfully')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    // Same ceiling as ProductResource's export — the "has
                    // stock" filter / search can still leave thousands of rows.
                    ini_set('memory_limit', '1024M');

                    // Respects whatever search + filters (e.g. "Only show
                    // items with stock") are currently applied to the
                    // listing, instead of always dumping every active product.
                    $productIds = $this->getTableQueryForExport()->pluck('id')->all();

                    return Excel::download(
                        new StockExport($productIds),
                        'stock-'.now()->format('Y-m-d').'.xlsx',
                    );
                }),
            Actions\Action::make('bulk')
                ->label('Bulk Add / Edit Stock')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->url(StockResource::getUrl('bulk')),
            Actions\Action::make('scanner')
                ->label('Scan Stock')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(StockScanner::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
