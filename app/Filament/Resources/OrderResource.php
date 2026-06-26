<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Package;
use App\Models\Product;
use App\Models\SponsorLogo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Orders';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── ORDER META ────────────────────────────────────────────────
            Forms\Components\Section::make('Order Details')
                ->schema([
                    Forms\Components\Select::make('club_id')
                        ->label('Club')
                        ->relationship('club', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Set $set) => $set('package_id', null)),

                    Forms\Components\Select::make('type')
                        ->options(['package' => 'Package', 'individual' => 'Individual Items'])
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
                        ->default('draft'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Order Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // ── PLAYER ROWS ───────────────────────────────────────────────
            Forms\Components\Section::make('Players & Items')
                ->description('Add players and assign sizes, sponsors, and extras for each item.')
                ->schema([
                    Forms\Components\Repeater::make('playerRows')
                        ->label('Players')
                        ->relationship('playerRows')
                        ->orderColumn('player_index')
                        ->schema([
                            // Player header
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('player_name')
                                    ->label('Player Name')
                                    ->placeholder('e.g. John Smith'),
                                Forms\Components\TextInput::make('number')
                                    ->label('Number')
                                    ->placeholder('#'),
                                Forms\Components\TextInput::make('initials')
                                    ->label('Initials')
                                    ->maxLength(5),
                                Forms\Components\Hidden::make('player_index'),
                            ]),

                            // Item cells
                            Forms\Components\Repeater::make('itemCells')
                                ->label('Items')
                                ->relationship('itemCells')
                                ->schema([
                                    Forms\Components\Grid::make(6)->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->label('Product')
                                            ->options(fn (Get $get) => self::getAvailableProducts($get))
                                            ->searchable()
                                            ->required()
                                            ->reactive()
                                            ->columnSpan(2),

                                        Forms\Components\Select::make('size')
                                            ->label('Size')
                                            ->options(fn (Get $get) => self::getSizesForProduct($get('product_id')))
                                            ->reactive(),

                                        Forms\Components\TextInput::make('qty')
                                            ->label('Qty')->numeric()->default(1)->minValue(1),

                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price')->numeric()->prefix('£')
                                            ->default(fn (Get $get) => self::getProductPrice($get('product_id'))),

                                        Forms\Components\TextInput::make('extra_cost')
                                            ->label('Extras')->numeric()->prefix('£')->default(0),
                                    ]),

                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\Select::make('sponsor_logo_id')
                                            ->label('Sponsor Logo')
                                            ->options(SponsorLogo::pluck('name', 'id'))
                                            ->searchable()
                                            ->nullable()
                                            ->reactive(),

                                        Forms\Components\Select::make('sponsor_position')
                                            ->label('Position on Item')
                                            ->options([
                                                'Front Chest Left'  => 'Front Chest Left',
                                                'Front Chest Right' => 'Front Chest Right',
                                                'Back Top'          => 'Back Top',
                                                'Back Centre'       => 'Back Centre',
                                                'Left Sleeve'       => 'Left Sleeve',
                                                'Right Sleeve'      => 'Right Sleeve',
                                                'Left Leg'          => 'Left Leg',
                                                'Right Leg'         => 'Right Leg',
                                                'Collar'            => 'Collar',
                                            ])
                                            ->visible(fn (Get $get) => filled($get('sponsor_logo_id'))),

                                        Forms\Components\Placeholder::make('line_total_display')
                                            ->label('Line Total')
                                            ->content(fn (Get $get) => '£' . number_format(
                                                (((float)($get('unit_price') ?? 0)) + ((float)($get('extra_cost') ?? 0))) * max(1, (int)($get('qty') ?? 1)),
                                                2
                                            )),
                                    ]),
                                ])
                                ->addActionLabel('+ Add Item')
                                ->collapsible()
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel('+ Add Player')
                        ->collapsible()
                        ->columnSpanFull()
                        ->defaultItems(0),
                ]),

            // ── ORDER TOTAL ───────────────────────────────────────────────
            Forms\Components\Section::make('Summary')
                ->schema([
                    Forms\Components\TextInput::make('total')
                        ->label('Order Total')
                        ->numeric()
                        ->prefix('£')
                        ->default(0)
                        ->readOnly(fn () => !auth()->user()?->isAdmin()),
                ])
                ->columns(1),
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
                Tables\Columns\TextColumn::make('total')->money('GBP')->sortable(),
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

    // ── Helpers ──────────────────────────────────────────────────────────

    private static function getAvailableProducts(Get $get): array
    {
        // Walk up to the order level to find club_id and type
        return Product::orderBy('name')->pluck('name', 'id')->toArray();
    }

    private static function getSizesForProduct(?int $productId): array
    {
        if (!$productId) return [];
        $product = Product::find($productId);
        if (!$product || !$product->size) return ['One Size' => 'One Size'];
        // For now, product has a single size field — in a real grouped-by-parent-sku
        // scenario you'd query siblings. Return available sizes from sibling products
        // with the same parent_sku.
        if ($product->parent_sku) {
            return Product::where('parent_sku', $product->parent_sku)
                ->whereNotNull('size')
                ->orderBy('size')
                ->pluck('size', 'size')
                ->toArray();
        }
        return [$product->size => $product->size];
    }

    private static function getProductPrice(?int $productId): float
    {
        if (!$productId) return 0;
        return (float) Product::find($productId)?->retail_price ?? 0;
    }
}
