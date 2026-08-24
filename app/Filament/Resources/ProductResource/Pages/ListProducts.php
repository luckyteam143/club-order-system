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
                        ->helperText('Columns: ID (optional, to update), Barcode, Parent SKU, Default SKU, Size, Name, Qty, On Backorder, Backorder Date, Retail Price, Attributes (comma-separated names).'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    // Large workbooks can take well past the server's default
                    // 30s / 128M FPM limits to parse + import; raise them just
                    // for this request rather than touching the shared php.ini.
                    set_time_limit(600);
                    ini_set('memory_limit', '512M');

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

                    if ($import->failures()->isNotEmpty()) {
                        Notification::make()
                            ->title('Import finished with ' . $import->failures()->count() . ' row error(s)')
                            ->body($import->failures()->map(fn ($f) => 'Row ' . $f->row() . ': ' . implode(' ', $f->errors()))->implode('; '))
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Products imported successfully')
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
