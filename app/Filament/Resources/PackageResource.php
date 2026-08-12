<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Package Details')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Select::make('club_id')
                    ->label('Club')
                    ->relationship('club', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive(),
                Forms\Components\TextInput::make('price')
                    ->required()->numeric()->default(0)->prefix('$'),
                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->required()->default('active'),
            ])->columns(2),

            Forms\Components\Section::make('Products in Package')->schema([
                // Deliberately NOT ->relationship('products') — Filament's
                // Repeater relationship-save treats every row without an
                // already-hydrated related record as a brand new model to
                // *create*, which tried to insert a blank Product row here.
                // State is saved manually via PersistsPackageItems instead.
                Forms\Components\Repeater::make('items')
                    ->label('Items')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->helperText('Only items assigned to this package\'s club (see the Clubs page) are offered — select a club above first.')
                            ->required()
                            ->searchable()
                            ->disabled(fn (Get $get) => blank($get('../../club_id')))
                            // The catalog runs into the thousands of products —
                            // search remotely instead of ->preload()ing every
                            // option, which was blowing past the memory limit.
                            // Also scoped to only the products assigned to
                            // this package's club (App\Models\Club::products).
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $clubId = $get('../../club_id');

                                if (blank($clubId)) {
                                    return [];
                                }

                                return Product::whereNull('parent_sku')
                                    ->whereHas('clubs', fn ($query) => $query->where('clubs.id', $clubId))
                                    ->where('name', 'like', "%{$search}%")
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id');
                            })
                            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('qty')
                            ->label('Qty')->numeric()->default(1)->minValue(1),
                        Forms\Components\TextInput::make('per_item_price')
                            ->label('Price Override')->numeric()->prefix('$')->nullable(),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->addActionLabel('Add Product')
                    ->default([])
                    ->reactive(),
            ]),

            // Kept as their own sections, separate from "Products in
            // Package" — same layout as the Order page's Sponsor Logos /
            // Embellishments sections. Predefined here, these auto-populate
            // an order's own Sponsor Logos / Embellishments sections the
            // moment this package's items are loaded — still fully editable
            // per-order afterward.
            Forms\Components\Section::make('Sponsor Logos')
                ->description('Only sponsor logos assigned to this package\'s club (see the Clubs page) are offered.')
                ->schema([
                    Forms\Components\Repeater::make('sponsors')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Item')
                                ->required()
                                ->options(fn (Get $get) => self::itemOptions($get('../../items')))
                                ->helperText('Add the item above first.'),
                            Forms\Components\Select::make('sponsor_logo_id')
                                ->label('Sponsor Logo')
                                ->required()
                                ->disabled(fn (Get $get) => blank($get('../../club_id')))
                                ->options(fn (Get $get) => blank($get('../../club_id'))
                                    ? []
                                    : \App\Models\SponsorLogo::where('club_id', $get('../../club_id'))->pluck('name', 'id'))
                                ->searchable(),
                            Forms\Components\Select::make('embellishment_position_id')
                                ->label('Position')
                                ->options(fn () => \App\Models\EmbellishmentPosition::pluck('name', 'id'))
                                ->searchable(),
                            Forms\Components\TextInput::make('brochure_link')
                                ->label('Brochure Link')
                                ->url(),
                            Forms\Components\TextInput::make('override_price')
                                ->label('Override Price')
                                ->numeric()->prefix('$')->nullable(),
                        ])
                        ->columns(5)
                        ->addActionLabel('Add Sponsor Logo')
                        ->default([]),
                ]),

            Forms\Components\Section::make('Embellishments')
                ->schema([
                    Forms\Components\Repeater::make('embellishments')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Item')
                                ->required()
                                ->options(fn (Get $get) => self::itemOptions($get('../../items')))
                                ->helperText('Add the item above first.'),
                            Forms\Components\Select::make('embellishment_id')
                                ->label('Embellishment')
                                ->required()
                                ->options(fn () => \App\Models\Embellishment::pluck('name', 'id'))
                                ->searchable(),
                            Forms\Components\Select::make('embellishment_position_id')
                                ->label('Position')
                                ->options(fn () => \App\Models\EmbellishmentPosition::pluck('name', 'id'))
                                ->searchable(),
                            Forms\Components\TextInput::make('override_price')
                                ->label('Override Price')
                                ->numeric()->prefix('$')->nullable(),
                        ])
                        ->columns(4)
                        ->addActionLabel('Add Embellishment')
                        ->default([]),
                ]),
        ]);
    }

    /** @param array<int, array{product_id?: int|string|null}>|null $items */
    private static function itemOptions(?array $items): array
    {
        $productIds = collect($items ?? [])->pluck('product_id')->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        return Product::whereIn('id', $productIds)->pluck('name', 'id')->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('price')->money('CAD')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'active', 'danger' => 'inactive']),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive']),
                Tables\Filters\SelectFilter::make('club')->relationship('club', 'name'),
            ])
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
            'index'  => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'edit'   => Pages\EditPackage::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();
    }
}
