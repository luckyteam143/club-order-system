<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogoTypeResource\Pages;
use App\Models\LogoType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LogoTypeResource extends Resource
{
    protected static ?string $model = LogoType::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Logo Type';

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
                Tables\Columns\TextColumn::make('logo_stocks_count')
                    ->label('Logo Stock Lines')
                    ->counts('logoStocks')
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
            'index'  => Pages\ListLogoTypes::route('/'),
            'create' => Pages\CreateLogoType::route('/create'),
            'edit'   => Pages\EditLogoType::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_logo_types') ?? false;
    }
}
