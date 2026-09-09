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
            Forms\Components\TextInput::make('qty_on_hold')
                ->label('On Hold')
                ->helperText('Reserved / set-aside units — held separately from the available quantity above.')
                ->required()
                ->numeric()
                ->default(0)
                ->minValue(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        // One row per item, one column per warehouse — mirrors the Bulk
        // Add/Edit Stock grid's layout instead of one row per
        // product+warehouse stock line.
        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return $table
            ->query(Product::query()->where('status', 'Active')->with('warehouseStocks'))
            // Same reason as ProductResource: ~30k rows each eager-loading a
            // relation — an "all" page size exhausts the request memory limit.
            ->paginated([25, 50, 100])
            ->columns([
                // The data-* cell attributes drive the mobile card layout in
                // list-stock-footer.blade.php: below ~768px the table collapses
                // to one card per product with each warehouse's stock stacked
                // and labelled underneath the product name instead of scrolling
                // off the side of a wide row.
                Tables\Columns\TextColumn::make('name')->label('Product')->searchable()->sortable()
                    ->extraCellAttributes(['data-mobile-heading' => 'true']),
                Tables\Columns\TextColumn::make('barcode')->label('Barcode')->searchable()->toggleable()
                    ->extraCellAttributes(['data-label' => 'Barcode']),
                Tables\Columns\TextColumn::make('size')->label('Size')->toggleable()
                    ->extraCellAttributes(['data-label' => 'Size']),
                Tables\Columns\TextColumn::make('total_look')->label('Total Look')->toggleable()
                    ->extraCellAttributes(['data-label' => 'Total Look'])
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
                    ->extraCellAttributes(['data-label' => $warehouse->name])
                    ->getStateUsing(fn (Product $record) => $record->warehouseStocks->firstWhere('warehouse_id', $warehouse->id)?->qty ?? 0))->all(),
                // Grand total of the on-hold reserve across every warehouse —
                // the per-warehouse breakdown sits inline in each warehouse
                // column next to its editable qty. Off by default to keep the
                // row compact.
                Tables\Columns\TextColumn::make('total_on_hold')->label('On Hold (all)')->numeric()->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['data-label' => 'On Hold (all)'])
                    ->tooltip('Reserved / set-aside units across all warehouses — not part of the available Total.')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->getStateUsing(fn (Product $record) => (int) $record->warehouseStocks->sum('qty_on_hold')),
                Tables\Columns\TextColumn::make('qty')->label('Total')->numeric()->sortable()->alignRight()
                    ->extraCellAttributes(['data-label' => 'Total'])
                    ->color(fn ($record) => match (true) {
                        $record->qty === 0 => 'danger',
                        $record->qty <= 5  => 'warning',
                        default            => 'success',
                    }),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['data-label' => 'Updated']),
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
            ->bulkActions([
                Tables\Actions\BulkAction::make('export')
                    ->label('Export Selected to Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        // Selected sets are normally small, but keep the same
                        // ceiling as the full export in case someone
                        // select-alls the whole list.
                        ini_set('memory_limit', '1024M');

                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\StockExport($records->pluck('id')->all()),
                            'stock-selected-' . now()->format('Y-m-d') . '.xlsx',
                        );
                    })
                    ->deselectRecordsAfterCompletion(),
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
