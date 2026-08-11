<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmbellishmentResource\Pages;
use App\Models\Embellishment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmbellishmentResource extends Resource
{
    protected static ?string $model = Embellishment::class;
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('cost')->required()->numeric()->default(0)->prefix('$'),
            Forms\Components\Select::make('embellishment_position_id')
                ->label('Position')
                ->relationship('position', 'name')
                ->searchable()
                ->preload(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cost')->money('CAD')->sortable(),
                Tables\Columns\TextColumn::make('position.name')->label('Position')->sortable()->toggleable(),
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
            'index'  => Pages\ListEmbellishments::route('/'),
            'create' => Pages\CreateEmbellishment::route('/create'),
            'edit'   => Pages\EditEmbellishment::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();
    }
}
