<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClubPackageResource\Pages;
use App\Models\Package;
use App\Models\PackageProduct;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Club panel only, read-only. Lists the packages an admin has built for
 * the signed-in user's club and lets them inspect everything in one —
 * items, crest settings, sponsor logos and embellishments — but never
 * create, edit or delete.
 */
class ClubPackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Package';

    protected static ?string $pluralLabel = 'Packages';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isClub();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
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
        return parent::getEloquentQuery()
            ->where('club_id', auth()->user()?->club_id)
            ->with([
                'packageProducts' => fn ($query) => $query->orderBy('sort_order'),
                'packageProducts.product:id,name,barcode',
                'packageProducts.sponsors.sponsorLogo:id,name',
                'packageProducts.sponsors.position:id,name',
                'packageProducts.embellishments.embellishment:id,name',
                'packageProducts.embellishments.position:id,name',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                Tables\Columns\TextColumn::make('price')->money('CAD')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('package_products_count')
                    ->label('Items')
                    ->counts('packageProducts')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No packages yet')
            ->emptyStateDescription('Packages your club can order are built by your Macron account manager and will appear here.');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Package')
                ->schema([
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('price')->money('CAD'),
                    Infolists\Components\TextEntry::make('status')->badge()
                        ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                ])
                ->columns(3),

            Infolists\Components\Section::make('Items')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('packageProducts')
                        ->hiddenLabel()
                        ->schema([
                            Infolists\Components\TextEntry::make('product.name')->label('Product'),
                            Infolists\Components\TextEntry::make('product.barcode')->label('Barcode')->placeholder('—'),
                            Infolists\Components\TextEntry::make('qty')->label('Qty'),
                            Infolists\Components\TextEntry::make('per_item_price')->label('Price Override')->money('CAD')->placeholder('—'),
                            Infolists\Components\IconEntry::make('has_club_crest')->label('Club Crest')->boolean(),
                            Infolists\Components\TextEntry::make('crest_number')->label('Crest #')
                                ->visible(fn (PackageProduct $record) => (bool) $record->has_club_crest),
                            Infolists\Components\IconEntry::make('is_goalie_item')->label('Goalkeeper item')->boolean(),
                            Infolists\Components\IconEntry::make('is_player_item')->label('Player item')->boolean(),
                        ])
                        ->columns(4),
                ]),

            Infolists\Components\Section::make('Sponsor Logos')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('sponsor_logos')
                        ->hiddenLabel()
                        ->state(fn (Package $record) => $record->packageProducts
                            ->flatMap(fn (PackageProduct $packageProduct) => $packageProduct->sponsors->map(fn ($sponsor) => [
                                'item' => $packageProduct->product?->name,
                                'sponsor_logo' => $sponsor->sponsorLogo?->name,
                                'position' => $sponsor->position?->name,
                                'brochure_link' => $sponsor->brochure_link,
                                'override_price' => $sponsor->override_price,
                            ]))
                            ->values()
                            ->all())
                        ->schema([
                            Infolists\Components\TextEntry::make('item')->label('Item'),
                            Infolists\Components\TextEntry::make('sponsor_logo')->label('Sponsor Logo'),
                            Infolists\Components\TextEntry::make('position')->label('Position')->placeholder('—'),
                            Infolists\Components\TextEntry::make('brochure_link')->label('Brochure')->url(fn (?string $state) => $state)->openUrlInNewTab()->placeholder('—'),
                            Infolists\Components\TextEntry::make('override_price')->label('Override Price')->money('CAD')->placeholder('—'),
                        ])
                        ->columns(5),
                ])
                ->visible(fn (Package $record) => $record->packageProducts->contains(fn (PackageProduct $p) => $p->sponsors->isNotEmpty())),

            Infolists\Components\Section::make('Embellishments')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('embellishments_list')
                        ->hiddenLabel()
                        ->state(fn (Package $record) => $record->packageProducts
                            ->flatMap(fn (PackageProduct $packageProduct) => $packageProduct->embellishments->map(fn ($embellishment) => [
                                'item' => $packageProduct->product?->name,
                                'embellishment' => $embellishment->embellishment?->name,
                                'position' => $embellishment->position?->name,
                                'override_price' => $embellishment->override_price,
                            ]))
                            ->values()
                            ->all())
                        ->schema([
                            Infolists\Components\TextEntry::make('item')->label('Item'),
                            Infolists\Components\TextEntry::make('embellishment')->label('Embellishment'),
                            Infolists\Components\TextEntry::make('position')->label('Position')->placeholder('—'),
                            Infolists\Components\TextEntry::make('override_price')->label('Override Price')->money('CAD')->placeholder('—'),
                        ])
                        ->columns(4),
                ])
                ->visible(fn (Package $record) => $record->packageProducts->contains(fn (PackageProduct $p) => $p->embellishments->isNotEmpty())),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClubPackages::route('/'),
            'view' => Pages\ViewClubPackage::route('/{record}'),
        ];
    }
}
