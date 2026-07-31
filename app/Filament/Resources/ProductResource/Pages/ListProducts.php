<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Imports\ProductsImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

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

                    $import = new ProductsImport();
                    Excel::import($import, $path);

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
