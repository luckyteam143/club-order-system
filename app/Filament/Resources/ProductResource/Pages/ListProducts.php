<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Imports\ProductsImport;
use App\Models\Product;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getFooter(): View
    {
        return view('filament.resources.product-resource.pages.list-products-footer');
    }

    /**
     * Batch-commits the rows currently in inline-edit mode — called only
     * from the footer's explicit "Save Changes" button, never on keystroke,
     * so an in-progress edit is never persisted until the user asks for it.
     *
     * @param  array<int|string, array<string, mixed>>  $edits  productId => [field => value]
     */
    public function saveInlineEdits(array $edits): void
    {
        $editableFields = ['status', 'macro_category', 'qty', 'retail_price', 'on_backorder'];

        foreach ($edits as $productId => $fields) {
            $product = Product::find($productId);

            if (! $product) {
                continue;
            }

            $updates = [];

            foreach ($editableFields as $field) {
                if (! array_key_exists($field, $fields)) {
                    continue;
                }

                $updates[$field] = $field === 'on_backorder' ? (bool) $fields[$field] : $fields[$field];
            }

            if ($updates) {
                $product->update($updates);
            }
        }

        Notification::make()
            ->title(count($edits).' product(s) updated')
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
                        ->directory('imports/products')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Only the columns present in your file are updated — any column you leave out keeps its current value. Include an ID or Barcode column so rows match existing products; a row matching neither creates a new product (Name required for that). Full column set: ID, Barcode, Parent SKU, Default SKU, Size, Name, Status, Macro Category, Product Description, Qty, On Backorder, Backorder Date, Retail Price, Attributes (comma-separated), Main Image, Gallery Links, Year, Available Until Year, Total Look, Weight, Color1/2 Code, Color1/2 Label.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    // Large workbooks can take well past the server's default
                    // time / per-worker memory (256M) to parse + import; raise
                    // them just for this request rather than touching the
                    // shared php.ini. A full-catalog sheet (~30k rows) hydrates
                    // a model per row plus attribute syncing and blew past 512M,
                    // so this matches the export ceiling with headroom to spare.
                    set_time_limit(600);
                    ini_set('memory_limit', '2048M');

                    try {
                        $import = new ProductsImport();
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

                    if ($import->errors) {
                        Notification::make()
                            ->title($import->imported . ' product(s) imported — ' . count($import->errors) . ' row(s) skipped')
                            ->body(implode(' ', array_slice($import->errors, 0, 15)))
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title($import->imported . ' product(s) imported / updated')
                            ->success()
                            ->send();
                    }
                }),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('products.export'))
                ->openUrlInNewTab(),
            Actions\CreateAction::make(),
        ];
    }
}
