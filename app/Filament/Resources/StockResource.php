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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('product.barcode')->label('Barcode')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                Tables\Columns\TextColumn::make('qty')->label('Qty')->numeric()->sortable()
                    ->color(fn ($record) => match (true) {
                        $record->qty === 0 => 'danger',
                        $record->qty <= 5  => 'warning',
                        default            => 'success',
                    }),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn () => Warehouse::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(fn (ProductWarehouseStock $record) => $record->product?->recalculateStock()),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('updated_at', 'desc');
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
        return auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();
    }
}
