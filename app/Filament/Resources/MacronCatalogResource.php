<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MacronCatalogResource\Pages;
use App\Models\MacronCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class MacronCatalogResource extends Resource
{
    protected static ?string $model = MacronCatalog::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 8;
    protected static ?string $modelLabel = 'Macron Catalog';
    protected static ?string $pluralModelLabel = 'Macron Catalogs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->helperText('Shown as the sidebar menu label, e.g. Football, Rugby, Sports.')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('year')
                ->label('Year')
                ->helperText('e.g. 2026 or 2026/27.')
                ->required()
                ->maxLength(9)
                ->default(date('Y')),
            Forms\Components\TextInput::make('link')
                ->label('Link')
                ->helperText('Full URL. Opens in a new tab from the sidebar.')
                ->url()
                ->required()
                ->maxLength(2048)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('show_in_menu')
                ->label('Show in main menu')
                ->helperText('When on, this catalog appears in the sidebar for users with the "view_macron_catalogs" permission.')
                ->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label('Sort order')
                ->helperText('Lower numbers appear first in the menu.')
                ->numeric()
                ->default(0)
                ->minValue(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('year')->label('Year')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('link')
                    ->label('Link')
                    ->url(fn (MacronCatalog $record) => $record->link)
                    ->openUrlInNewTab()
                    ->limit(50)
                    ->tooltip(fn (MacronCatalog $record) => $record->link),
                Tables\Columns\ToggleColumn::make('show_in_menu')->label('In menu'),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->options(fn () => Schema::hasTable('macron_catalogs')
                        ? MacronCatalog::query()->orderByDesc('year')->distinct()->pluck('year', 'year')->all()
                        : []),
                Tables\Filters\TernaryFilter::make('show_in_menu')->label('Shown in menu'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMacronCatalogs::route('/'),
            'create' => Pages\CreateMacronCatalog::route('/create'),
            'edit'   => Pages\EditMacronCatalog::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_macron_catalogs') ?? false;
    }
}
