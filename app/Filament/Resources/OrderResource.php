<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Embellishment;
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
                        ->default('draft')
                        ->disabled(fn () => !auth()->user()?->isAdmin())
                        ->dehydrated()
                        ->helperText(fn () => auth()->user()?->isAdmin()
                            ? null
                            : 'New orders save as Draft. Use "Submit Order" below when it\'s ready — you can keep editing until then.'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Order Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // ── SPREADSHEET GRID ─────────────────────────────────────────
            Forms\Components\Section::make('Players & Items')
                ->description('Fill this in like a spreadsheet: type across cells, Tab/Enter to move, or paste a roster copied straight from Excel. Item columns carry their own sponsor logo / embellishment; each cell picks a size for that player.')
                ->schema([
                    Forms\Components\ViewField::make('grid_state')
                        ->view('filament.forms.order-grid')
                        ->viewData([
                            'products'       => self::productsCatalogForGrid(),
                            'sponsorLogos'   => self::sponsorLogosCatalogForGrid(),
                            'embellishments' => self::embellishmentsCatalogForGrid(),
                            'packages'       => self::packagesCatalogForGrid(),
                        ])
                        ->default(json_encode(['columns' => [], 'rows' => []]))
                        ->dehydrateStateUsing(fn ($state) => is_string($state) ? $state : json_encode($state))
                        ->columnSpanFull(),
                ]),

            // ── ORDER TOTAL ───────────────────────────────────────────────
            Forms\Components\Section::make('Summary')
                ->schema([
                    Forms\Components\TextInput::make('total')
                        ->label('Order Total')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->readOnly(fn () => !auth()->user()?->isAdmin())
                        ->helperText('Recalculated automatically from the grid when you save.'),
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
        return Product::whereNull('parent_sku')
            ->where('status', 'Active')
            ->with('attributes')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id'    => $product->id,
                'name'  => $product->name,
                'price' => (float) $product->retail_price,
                'sizes' => $product->attributes->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();
    }

    private static function sponsorLogosCatalogForGrid(): array
    {
        return SponsorLogo::orderBy('name')->get()
            ->map(fn (SponsorLogo $logo) => [
                'id'      => $logo->id,
                'name'    => $logo->name,
                'price'   => (float) $logo->price,
                'club_id' => $logo->club_id,
            ])
            ->values()
            ->all();
    }

    private static function embellishmentsCatalogForGrid(): array
    {
        return Embellishment::orderBy('name')->get()
            ->map(fn (Embellishment $e) => [
                'id'   => $e->id,
                'name' => $e->name,
                'cost' => (float) $e->cost,
            ])
            ->values()
            ->all();
    }

    private static function packagesCatalogForGrid(): array
    {
        return Package::with('products.attributes')->get()
            ->map(fn (Package $package) => [
                'id'      => $package->id,
                'club_id' => $package->club_id,
                'items'   => $package->products->map(fn (Product $product) => [
                    'product_id' => $product->id,
                    'price'      => (float) ($product->pivot->per_item_price ?? $product->retail_price),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
