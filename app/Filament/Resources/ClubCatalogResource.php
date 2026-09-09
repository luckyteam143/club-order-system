<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClubCatalogResource\Pages;
use App\Models\Product;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Club panel only, read-only. Lists the parent products an admin has
 * assigned to the signed-in user's club (the `club_product` pivot, managed
 * from ClubResource > Club Items), together with that club's own prices and
 * crest settings. These are the items offered when the club places a "Club
 * Items" order.
 */
class ClubCatalogResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Club Catalog';

    protected static ?string $pluralLabel = 'Club Catalog';

    protected static ?string $navigationLabel = 'Club Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    /** Only ever shown in the club panel, and only to a club user. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isClub();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $clubId = auth()->user()?->club_id;

        return parent::getEloquentQuery()
            ->whereHas('clubs', fn (Builder $query) => $query->whereKey($clubId))
            // Pull only THIS club's pivot row so the price / crest columns
            // read straight off it.
            ->with(['clubs' => fn ($query) => $query->whereKey($clubId)]);
    }

    /** This club's `club_product` pivot row for a listed product. */
    protected static function clubPivot(Product $record): ?object
    {
        return $record->clubs->first()?->pivot;
    }

    /** http:// PIM image URLs would be blocked as mixed content on our https panel. */
    protected static function secureImage(?string $url): ?string
    {
        return $url ? preg_replace('#^http://#i', 'https://', $url) : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\ImageColumn::make('image_link')
                    ->label('Image')
                    ->getStateUsing(fn (Product $record) => static::secureImage($record->image_link))
                    ->height(56)
                    ->square()
                    ->extraImgAttributes(['style' => 'object-fit:contain;background:#fff;border-radius:6px;padding:2px;'])
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('medium')->wrap(),
                Tables\Columns\TextColumn::make('default_sku')->label('SKU')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('barcode')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('macro_category')->label('Category')->badge()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('club_price')
                    ->label('Club Price')
                    ->state(fn (Product $record) => static::clubPivot($record)?->club_price)
                    ->money('CAD')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('online_store_price')
                    ->label('Online Store Price')
                    ->state(fn (Product $record) => static::clubPivot($record)?->online_store_price)
                    ->money('CAD')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('retail_price')
                    ->label('Retail Price')
                    ->money('CAD')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('has_club_crest')
                    ->label('Club Crest')
                    ->boolean()
                    ->state(fn (Product $record) => (bool) (static::clubPivot($record)?->has_club_crest)),
                Tables\Columns\TextColumn::make('crest_number')
                    ->label('Crest #')
                    ->state(fn (Product $record) => (static::clubPivot($record)?->has_club_crest)
                        ? static::clubPivot($record)?->crest_number
                        : null)
                    ->placeholder('—')
                    ->alignCenter()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('macro_category')
                    ->label('Category')
                    ->options(fn () => static::getEloquentQuery()
                        ->whereNotNull('macro_category')
                        ->distinct()
                        ->orderBy('macro_category')
                        ->pluck('macro_category', 'macro_category')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No catalog items yet')
            ->emptyStateDescription('Your Macron account manager assigns the items your club can order — they will appear here once set up.');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Split::make([
                Infolists\Components\ImageEntry::make('image_link')
                    ->hiddenLabel()
                    ->getStateUsing(fn (Product $record) => static::secureImage($record->image_link))
                    ->height(220)
                    ->square()
                    ->grow(false),
                Infolists\Components\Grid::make(2)->schema([
                    Infolists\Components\TextEntry::make('name')->columnSpanFull()->weight('bold')->size('lg'),
                    Infolists\Components\TextEntry::make('default_sku')->label('SKU')->placeholder('—'),
                    Infolists\Components\TextEntry::make('barcode')->placeholder('—'),
                    Infolists\Components\TextEntry::make('macro_category')->label('Category')->badge()->placeholder('—'),
                    Infolists\Components\TextEntry::make('size')->placeholder('—'),
                    Infolists\Components\TextEntry::make('club_price')
                        ->label('Club Price')
                        ->state(fn (Product $record) => static::clubPivot($record)?->club_price)
                        ->money('CAD')->placeholder('—'),
                    Infolists\Components\TextEntry::make('online_store_price')
                        ->label('Online Store Price')
                        ->state(fn (Product $record) => static::clubPivot($record)?->online_store_price)
                        ->money('CAD')->placeholder('—'),
                    Infolists\Components\IconEntry::make('has_club_crest')
                        ->label('Club Crest')
                        ->boolean()
                        ->state(fn (Product $record) => (bool) (static::clubPivot($record)?->has_club_crest)),
                    Infolists\Components\TextEntry::make('crest_number')
                        ->label('Crest #')
                        ->state(fn (Product $record) => (static::clubPivot($record)?->has_club_crest)
                            ? static::clubPivot($record)?->crest_number
                            : null)
                        ->placeholder('—'),
                ])->grow(true),
            ])->from('md'),
            Infolists\Components\TextEntry::make('description')
                ->columnSpanFull()
                ->html()
                ->placeholder('No description provided.'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClubCatalog::route('/'),
        ];
    }
}
