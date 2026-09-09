<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\MediaResource;
use App\Imports\MediaImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListMedia extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        // Import (which can register files dropped onto the server by hand)
        // and the full-library Export are for media administrators only.
        // Club users still get "New media" to build their own scoped
        // library (subject to create_media).
        $seesAllMedia = MediaResource::seesAllMedia();

        return [
            Actions\Action::make('import')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible($seesAllMedia && (auth()->user()?->can('edit_media') ?? false))
                ->modalDescription('Bulk-rename library entries, or register images you copied into storage/app/public/media/ by hand.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV File')
                        ->disk('local')
                        ->directory('imports/media')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: Media ID (optional), Name, File Name. Matches the Export Excel layout.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $import = new MediaImport;
                        Excel::import($import, $path);
                    } catch (\Throwable $e) {
                        Storage::disk('local')->delete($data['file']);

                        Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Storage::disk('local')->delete($data['file']);

                    $body = "{$import->imported} row(s) applied.";

                    if ($import->errors !== []) {
                        Notification::make()
                            ->title('Import finished with '.count($import->errors).' issue(s)')
                            ->body($body.' '.implode(' ', array_slice($import->errors, 0, 10))
                                .(count($import->errors) > 10 ? ' …' : ''))
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()->title('Media imported')->body($body)->success()->send();
                    }
                }),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible($seesAllMedia)
                ->url(route('media.export'))
                ->openUrlInNewTab(),
            Actions\CreateAction::make()->label('New media'),
        ];
    }
}
