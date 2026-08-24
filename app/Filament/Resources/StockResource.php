<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockResource\Pages;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class StockResource extends Resource
{
    protected static ?string $model = ProductWarehouseStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';
    protected static ?string $navigationLabel = 'Stock';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Product')
                ->required()
                ->searchable()
                // The catalog runs into the thousands of products — search
                // remotely instead of ->preload()ing every option.
                ->getSearchResultsUsing(fn (string $search) => Product::where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orderBy('name')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Product $product) => [$product->id => $product->name.($product->barcode ? " ({$product->barcode})" : '')]))
                ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                ->unique(
                    table: 'product_warehouse_stock',
                    column: 'product_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('warehouse_id', $get('warehouse_id')),
                )
                ->validationMessages(['unique' => 'This product already has a stock line for the selected warehouse — edit that one instead.']),
            Forms\Components\Select::make('warehouse_id')
                ->label('Warehouse')
                ->relationship('warehouse', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->reactive(),
            Forms\Components\TextInput::make('qty')
                ->label('Quantity')
                ->required()
                ->numeric()
                ->default(0)
                ->minValue(0),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        // One row per item, one column per warehouse — mirrors the Bulk
        // Add/Edit Stock grid's layout instead of one row per
        // product+warehouse stock line.
        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return $table
            ->query(Product::query()->where('status', 'Active')->with('warehouseStocks'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('barcode')->label('Barcode')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('size')->label('Size')->toggleable(),
                Tables\Columns\TextColumn::make('total_look')->label('Total Look')->toggleable()
                    // Some values (e.g. "NO TOTAL LOOK") run long enough to
                    // stretch the whole table — cap the column width and
                    // wrap onto multiple lines instead of growing wide.
                    ->wrap()
                    ->extraAttributes(['class' => 'max-w-[140px]'])
                    // total_look only lives on the parent product row, not
                    // its size-variant children — searching it needs to
                    // also pull in every child whose parent matches, or a
                    // search like "Thunder" would only ever surface the
                    // (non-stockable) parent itself.
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query->where('total_look', 'like', "%{$search}%")
                                ->orWhereIn('parent_sku', function ($subQuery) use ($search) {
                                    $subQuery->select('default_sku')
                                        ->from('products')
                                        ->whereNull('parent_sku')
                                        ->where('total_look', 'like', "%{$search}%");
                                });
                        });
                    }),
                ...$warehouses->map(fn (Warehouse $warehouse) => Tables\Columns\ViewColumn::make('warehouse_'.$warehouse->id)
                    ->label($warehouse->name)
                    ->view('filament.tables.columns.stock-editable-cell')
                    ->getStateUsing(fn (Product $record) => $record->warehouseStocks->firstWhere('warehouse_id', $warehouse->id)?->qty ?? 0))->all(),
                Tables\Columns\TextColumn::make('qty')->label('Total')->numeric()->sortable()->alignRight()
                    ->color(fn ($record) => match (true) {
                        $record->qty === 0 => 'danger',
                        $record->qty <= 5  => 'warning',
                        default            => 'success',
                    }),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_stock')
                    ->label('Only show items with stock')
                    // Product.qty is the running total across every
                    // warehouse (kept in sync by recalculateStock()) — items
                    // with 0 everywhere are hidden while this is on, nothing
                    // is ever deleted.
                    ->query(fn (Builder $query) => $query->where('qty', '>', 0))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Product $record) => StockResource::getUrl('bulk', ['q' => $record->barcode ?: $record->name])),
            ])
            // Otherwise Filament defaults a whole-row click to this row
            // action's URL, which fights with clicking a warehouse cell to
            // edit its quantity inline.
            ->recordUrl(null)
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStock::route('/'),
            'create' => Pages\CreateStock::route('/create'),
            'bulk'   => Pages\BulkStock::route('/bulk'),
            'edit'   => Pages\EditStock::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_stock') ?? false;
    }
}
