<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClubTeamResource\Pages;
use App\Models\ClubTeam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClubTeamResource extends Resource
{
    protected static ?string $model = ClubTeam::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Club Team';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('club_id')
                ->label('Club')
                ->relationship('club', 'name')
                ->searchable()
                ->preload()
                ->required()
                // Club users only ever add teams for their own club — lock
                // the field to it instead of offering every club.
                ->disabled(fn () => auth()->user()?->isClub())
                ->dehydrated()
                ->default(fn () => auth()->user()?->isClub() ? auth()->user()->club_id : null),
            Forms\Components\TextInput::make('team_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('po_reference')->label('PO Reference')->maxLength(255),
            Forms\Components\TextInput::make('coach_manager_name')->label('Coach / Manager Name')->maxLength(255),
            Forms\Components\TextInput::make('coach_manager_email')->label('Coach / Manager Email')->email()->maxLength(255),
            Forms\Components\TextInput::make('coach_manager_contact')->label('Coach / Manager Contact')->tel()->maxLength(255),
            Forms\Components\Textarea::make('address')->rows(2)->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                ->default('active')
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();

                if ($user?->isClub()) {
                    $query->where('club_id', $user->club_id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('team_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->searchable()->sortable()
                    ->visible(fn () => ! auth()->user()?->isClub()),
                Tables\Columns\TextColumn::make('po_reference')->label('PO Reference')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('coach_manager_name')->label('Coach / Manager')->searchable(),
                Tables\Columns\TextColumn::make('coach_manager_email')->label('Email')->toggleable(),
                Tables\Columns\TextColumn::make('coach_manager_contact')->label('Contact')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'active', 'gray' => 'inactive']),
                Tables\Columns\TextColumn::make('sort_order')->label('Sort')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive']),
                Tables\Filters\SelectFilter::make('club')->relationship('club', 'name')
                    ->visible(fn () => ! auth()->user()?->isClub()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            // Drag handle reorders by writing sequential sort_order values —
            // for an admin viewing every club at once (no club filter
            // applied), dragging only makes sense within one club at a time,
            // same as the club filter already offered above.
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClubTeams::route('/'),
            'create' => Pages\CreateClubTeam::route('/create'),
            'edit'   => Pages\EditClubTeam::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->can('manage_club_teams') || $user?->isClub() || false;
    }
}
