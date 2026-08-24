<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmbellishmentPositionResource\Pages;
use App\Models\EmbellishmentPosition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmbellishmentPositionResource extends Resource
{
    protected static ?string $model = EmbellishmentPosition::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 6;
    protected static ?string $label = 'Embellishment Position';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('embellishments_count')
                    ->label('Embellishments')
                    ->counts('embellishments')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmbellishmentPositions::route('/'),
            'create' => Pages\CreateEmbellishmentPosition::route('/create'),
            'edit'   => Pages\EditEmbellishmentPosition::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_embellishment_positions') ?? false;
    }
}
