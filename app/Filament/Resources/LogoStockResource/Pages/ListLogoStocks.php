<?php

namespace App\Filament\Resources\LogoStockResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Pages\LogoStockScanner;
use App\Filament\Resources\LogoStockResource;
use App\Imports\LogoStockImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListLogoStocks extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = LogoStockResource::class;

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
                        ->directory('imports/logo-stock')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: Logo Stock ID (optional), Club Name and/or Club Code, Barcode, Logo Type, Logo Name, Size, Location, Warehouse (name or code), Position, Qty, Vector File Link. Matches the Export Excel layout.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    set_time_limit(600);
                    ini_set('memory_limit', '512M');

                    try {
                        $import = new LogoStockImport;
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
                            ->title('Import finished with '.$import->failures()->count().' row error(s)')
                            ->body($import->failures()->map(fn ($f) => 'Row '.$f->row().': '.implode(' ', $f->errors()))->implode('; '))
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Logos stock imported successfully')
                            ->success()
                            ->send();
                    }
                }),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('logo-stock.export'))
                ->openUrlInNewTab(),
            Actions\Action::make('bulk')
                ->label('Bulk Add / Edit Logos Stock')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->url(LogoStockResource::getUrl('bulk')),
            Actions\Action::make('scanner')
                ->label('Scan Logos Stock')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(LogoStockScanner::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
