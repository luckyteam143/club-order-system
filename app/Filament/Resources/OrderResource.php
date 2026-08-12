<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Club;
use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageProduct;
use App\Models\Product;
use App\Models\SponsorLogo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Orders';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── ORDER META ── three sections side by side, one column each ──
            // Grid::make(3) alone only applies 3 columns from the `lg`
            // breakpoint up; forcing it at `default` keeps all three side by
            // side instead of stacking into full-width rows on narrower
            // admin viewports.
            Forms\Components\Grid::make(['default' => 3])
                ->schema([
                    Forms\Components\Section::make('Order')
                        ->compact()
                        // Section defaults to columnSpan('full') internally,
                        // which would make it fill the whole 3-col grid row
                        // by itself — pin it to exactly 1 column instead.
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\Select::make('club_id')
                                ->label('Club')
                                ->relationship('club', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('package_id', null);

                                    if ($club = Club::find($state)) {
                                        $set('shipping_address', $club->address);
                                        $set('phone', $club->phone);
                                        $set('email', $club->email);
                                    }
                                }),

                            Forms\Components\Select::make('type')
                                ->options([
                                    'package'    => 'Package',
                                    'individual' => 'Individual Items',
                                    'club_items' => 'Club Items',
                                ])
                                ->required()
                                ->default('individual')
                                ->reactive(),

                            Forms\Components\Select::make('package_id')
                                ->label('Package')
                                ->options(fn (Get $get) => Package::where('club_id', $get('club_id'))
                                    ->where('status', 'active')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->visible(fn (Get $get) => $get('type') === 'package')
                                ->reactive(),

                            Forms\Components\Select::make('status')
                                ->options([
                                    'draft'      => 'Draft',
                                    'submitted'  => 'Submitted',
                                    'admin_edit' => 'Admin Edit',
                                    'completed'  => 'Completed',
                                ])
                                ->required()
                                ->default('draft')
                                ->disabled(fn () => !auth()->user()?->isAdmin())
                                ->dehydrated()
                                ->helperText(fn () => auth()->user()?->isAdmin()
                                    ? null
                                    : 'New orders save as Draft — "Submit Order" below when ready.'),

                            Forms\Components\Textarea::make('notes')
                                ->label('Order Notes')
                                ->rows(2),
                        ])
                        ->columns(1),

                    Forms\Components\Section::make('Order Details')
                        ->compact()
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\TextInput::make('team_po')
                                ->label('Team / PO #'),
                            Forms\Components\TextInput::make('coach_manager')
                                ->label('Coach / Manager'),
                            Forms\Components\TextInput::make('shipping_address')
                                ->label('Shipping Address')
                                ->helperText('Prefilled from the club once selected — editable.'),
                            Forms\Components\TextInput::make('phone')
                                ->label('Phone')
                                ->tel(),
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email(),
                        ])
                        ->columns(1),

                    Forms\Components\Section::make('For Office Use')
                        ->compact()
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\DatePicker::make('order_date')
                                ->label('Order Date')
                                ->default(now()),
                            Forms\Components\TextInput::make('b2b_number')
                                ->label('B2B Number'),
                            Forms\Components\TextInput::make('qb_invoice')
                                ->label('QB Invoice #'),
                            Forms\Components\TextInput::make('brochure_link')
                                ->label('Link to Brochure')
                                ->url(),
                        ])
                        ->columns(1)
                        ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin()),
                ]),

            // ── THE SHEET ──────────────────────────────────────────────────
            Forms\Components\ViewField::make('grid_state')
                ->view('filament.forms.order-grid')
                ->viewData([
                    'products'               => self::productsCatalogForGrid(),
                    'sponsorLogos'           => self::sponsorLogosCatalogForGrid(),
                    'embellishments'         => self::embellishmentsCatalogForGrid(),
                    'embellishmentPositions' => self::embellishmentPositionsCatalogForGrid(),
                    'packages'               => self::packagesCatalogForGrid(),
                    'clubItems'              => self::clubItemsCatalogForGrid(),
                ])
                ->default(json_encode(['columns' => [], 'rows' => [], 'sponsors' => [], 'embellishments' => []]))
                ->dehydrateStateUsing(fn ($state) => is_string($state) ? $state : json_encode($state))
                ->columnSpanFull(),

            Forms\Components\Hidden::make('total')->default(0),
        ]);
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
                Tables\Columns\TextColumn::make('id')->label('Order #')->sortable(),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors(['primary' => 'package', 'success' => 'individual']),
                Tables\Columns\TextColumn::make('package.name')->label('Package')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'primary'   => 'submitted',
                        'warning'   => 'admin_edit',
                        'success'   => 'completed',
                    ]),
                Tables\Columns\TextColumn::make('total')->money('CAD')->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'      => 'Draft',
                        'submitted'  => 'Submitted',
                        'admin_edit' => 'Admin Edit',
                        'completed'  => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options(['package' => 'Package', 'individual' => 'Individual']),
                Tables\Filters\SelectFilter::make('club')->relationship('club', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'draft' && auth()->user()?->club_id === $record->club_id)
                    ->action(function ($record) {
                        $record->update([
                            'status'       => 'submitted',
                            'submitted_at' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn ($record) => route('orders.export', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    // ── Grid catalog data ───────────────────────────────────────────────

    private static function productsCatalogForGrid(): array
    {
        // The live catalog can run into the thousands of rows with tens of
        // thousands of attribute pivot rows. Hydrating those as Eloquent
        // models (each attribute row becomes a full Attribute model wrapped
        // in a Pivot instance) is what actually blows the memory limit —
        // pull everything through the query builder instead, which returns
        // lightweight stdClass rows.
        $products = \Illuminate\Support\Facades\DB::table('products')
            ->select(['id', 'name', 'retail_price'])
            ->whereNull('parent_sku')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $sizesByProductId = \Illuminate\Support\Facades\DB::table('attribute_product')
            ->join('attributes', 'attributes.id', '=', 'attribute_product.attribute_id')
            ->whereIn('attribute_product.product_id', $products->pluck('id'))
            ->select(['attribute_product.product_id', 'attributes.name'])
            ->get()
            ->groupBy('product_id');

        return $products
            ->map(fn ($product) => [
                'id'    => $product->id,
                'name'  => $product->name,
                'price' => (float) $product->retail_price,
                'sizes' => ($sizesByProductId->get($product->id) ?? collect())->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();
    }

    private static function sponsorLogosCatalogForGrid(): array
    {
        return SponsorLogo::with('position')->orderBy('name')->get()
            ->map(fn (SponsorLogo $logo) => [
                'id'          => $logo->id,
                'name'        => $logo->name,
                'price'       => (float) $logo->price,
                'club_id'     => $logo->club_id,
                'position_id' => $logo->embellishment_position_id,
            ])
            ->values()
            ->all();
    }

    private static function embellishmentPositionsCatalogForGrid(): array
    {
        return EmbellishmentPosition::orderBy('name')->get()
            ->map(fn (EmbellishmentPosition $p) => [
                'id'   => $p->id,
                'name' => $p->name,
            ])
            ->values()
            ->all();
    }

    private static function embellishmentsCatalogForGrid(): array
    {
        return Embellishment::orderBy('name')->get()
            ->map(fn (Embellishment $e) => [
                'id'          => $e->id,
                'name'        => $e->name,
                'cost'        => (float) $e->cost,
                'position_id' => $e->embellishment_position_id,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<int>> club id => assigned product ids */
    private static function clubItemsCatalogForGrid(): array
    {
        $pairs = \Illuminate\Support\Facades\DB::table('club_product')->select(['club_id', 'product_id'])->get();

        return $pairs->groupBy('club_id')
            ->map(fn ($rows) => $rows->pluck('product_id')->values()->all())
            ->all();
    }

    private static function packagesCatalogForGrid(): array
    {
        // Queried directly off package_product (rather than through
        // Package::products()) so the sponsor/embellishment predefined on
        // each package item can be eager-loaded in one shot.
        $packageProducts = PackageProduct::with(['sponsors', 'embellishments'])->get();

        $productPrices = Product::whereIn('id', $packageProducts->pluck('product_id')->unique())
            ->pluck('retail_price', 'id');

        $packageProductsByPackage = $packageProducts->groupBy('package_id');

        return Package::all()->map(function (Package $package) use ($packageProductsByPackage, $productPrices) {
            $items = ($packageProductsByPackage->get($package->id) ?? collect())
                ->map(fn (PackageProduct $packageProduct) => [
                    'product_id'     => $packageProduct->product_id,
                    'price'          => (float) ($packageProduct->per_item_price ?? $productPrices->get($packageProduct->product_id) ?? 0),
                    'sponsors'       => $packageProduct->sponsors->map(fn ($s) => [
                        'sponsor_logo_id'           => $s->sponsor_logo_id,
                        'embellishment_position_id' => $s->embellishment_position_id,
                        'brochure_link'             => $s->brochure_link,
                        'override_price'            => is_null($s->override_price) ? null : (float) $s->override_price,
                    ])->values()->all(),
                    'embellishments' => $packageProduct->embellishments->map(fn ($e) => [
                        'embellishment_id'          => $e->embellishment_id,
                        'embellishment_position_id' => $e->embellishment_position_id,
                        'override_price'            => is_null($e->override_price) ? null : (float) $e->override_price,
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            return [
                'id'      => $package->id,
                'club_id' => $package->club_id,
                'price'   => (float) $package->price,
                'items'   => $items,
            ];
        })->values()->all();
    }
}
