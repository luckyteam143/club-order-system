<?php

namespace App\Filament\Resources\BrochureResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\BrochureResource;
use App\Imports\BrochuresImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListBrochures extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = BrochureResource::class;

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
                        ->directory('imports/brochures')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: ID (optional, to update), Club Name, Club Code, Brochure Link, Created Date, Approved Date (dates as YYYY-MM-DD, leave Approved Date blank if not yet approved).'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $import = new BrochuresImport;
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
                            ->title('Brochures imported successfully')
                            ->success()
                            ->send();
                    }
                }),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('brochures.export'))
                ->openUrlInNewTab(),
            Actions\CreateAction::make(),
        ];
    }
}
