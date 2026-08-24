<?php

namespace App\Filament\Resources\ClubResource\Pages;

use App\Filament\Resources\ClubResource;
use App\Imports\ClubsImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListClubs extends ListRecords
{
    protected static string $resource = ClubResource::class;

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
                        ->directory('imports/clubs')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: ID (optional, to update), Name, Club Code, Email, Phone, Address, Status (active/inactive), Summer Forecast Open, Summer Forecast Close, Winter Forecast Open, Winter Forecast Close (dates as YYYY-MM-DD).'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $import = new ClubsImport();
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
                            ->title('Clubs imported successfully')
                            ->success()
                            ->send();
                    }
                }),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('clubs.export'))
                ->openUrlInNewTab(),
            Actions\CreateAction::make(),
        ];
    }
}
