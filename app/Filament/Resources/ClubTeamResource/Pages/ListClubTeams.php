<?php

namespace App\Filament\Resources\ClubTeamResource\Pages;

use App\Filament\Resources\ClubTeamResource;
use App\Imports\ClubTeamImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListClubTeams extends ListRecords
{
    protected static string $resource = ClubTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                // Admin/staff only — ClubTeamImport isn't club-scoped (a
                // sheet can carry rows for any club), same as Logos Stock's
                // import/export being an admin-only bulk tool.
                ->visible(fn () => auth()->user()?->can('manage_club_teams'))
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV File')
                        ->disk('local')
                        ->directory('imports/club-teams')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: Team ID (optional), Club Name and/or Club Code, Team Name, PO Reference, Coach Manager Name/Email/Contact, Address, Status, Sort Order. Matches the Export Excel layout.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    set_time_limit(600);
                    ini_set('memory_limit', '512M');

                    try {
                        $import = new ClubTeamImport();
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
                            ->title('Club teams imported successfully')
                            ->success()
                            ->send();
                    }
                }),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('manage_club_teams'))
                ->url(route('club-teams.export'))
                ->openUrlInNewTab(),
            Actions\CreateAction::make(),
        ];
    }
}
