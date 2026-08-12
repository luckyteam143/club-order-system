<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product Info')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('barcode')->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('parent_sku')
                    ->helperText('Leave blank if this is a parent/master product.'),
                Forms\Components\TextInput::make('default_sku'),
                Forms\Components\TextInput::make('size'),
                Forms\Components\Select::make('status')
                    ->options(['Active' => 'Active', 'Inactive' => 'Inactive'])
                    ->default('Active')
                    ->required(),
                Forms\Components\TextInput::make('macro_category')
                    ->label('Macro Category')
                    ->datalist(fn () => \App\Models\Product::whereNotNull('macro_category')
                        ->distinct()
                        ->orderBy('macro_category')
                        ->pluck('macro_category')
                        ->all()),
                Forms\Components\Select::make('co_sponsorship_id')
                    ->label('Co-Sponsorship')
                    ->relationship('coSponsorship', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Only assign on the parent product.'),
            ])->columns(2),

            Forms\Components\Section::make('Stock & Pricing')->schema([
                Forms\Components\TextInput::make('qty')
                    ->label('Stock Qty')->required()->numeric()->default(0)->minValue(0),
                Forms\Components\TextInput::make('retail_price')
                    ->label('Retail Price')->required()->numeric()->default(0)->prefix('$'),
                Forms\Components\Toggle::make('on_backorder')->label('On Backorder')->reactive(),
                Forms\Components\DatePicker::make('backorder_date')
                    ->label('Expected Back In Stock')
                    ->visible(fn ($get) => $get('on_backorder')),
            ])->columns(2),

            Forms\Components\Section::make('Media')->schema([
                Forms\Components\TextInput::make('image_link')->label('Image URL')->url()->columnSpanFull(),
                Forms\Components\Textarea::make('gallery_links')
                    ->label('Gallery Image URLs')
                    ->helperText('Comma-separated list of URLs.')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Classification')->schema([
                Forms\Components\TextInput::make('year'),
                Forms\Components\TextInput::make('available_until_year')->label('Available Until Year'),
                Forms\Components\TextInput::make('total_look')->label('Total Look'),
                Forms\Components\TextInput::make('weight')->numeric()->step(0.01),
            ])->columns(4),

            Forms\Components\Section::make('Colors')->schema([
                Forms\Components\TextInput::make('color1_code')->label('Color 1 Code'),
                Forms\Components\TextInput::make('color1_label')->label('Color 1 Label'),
                Forms\Components\TextInput::make('color2_code')->label('Color 2 Code'),
                Forms\Components\TextInput::make('color2_label')->label('Color 2 Label'),
            ])->columns(4),

            Forms\Components\Section::make('Attributes')->schema([
                Forms\Components\Select::make('attributes')
                    ->label('Sizes / Attributes')
                    ->relationship('attributes', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->wrap(),
                Tables\Columns\TextColumn::make('default_sku')->label('Default SKU')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('size')->searchable(),
                Tables\Columns\TextColumn::make('parent_sku')->label('Parent SKU')->searchable()->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'Active', 'danger' => 'Inactive']),
                Tables\Columns\TextColumn::make('macro_category')->label('Macro Category')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('qty')->label('Stock')->numeric()->sortable()
                    ->color(fn ($record) => match(true) {
                        $record->qty === 0 => 'danger',
                        $record->qty <= 5  => 'warning',
                        default            => 'success',
                    }),
                Tables\Columns\IconColumn::make('on_backorder')->label('Backorder')->boolean(),
                Tables\Columns\TextColumn::make('retail_price')->label('Price')->money('CAD')->sortable(),
                Tables\Columns\TextColumn::make('coSponsorship.name')->label('Co-Sponsorship')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attributes.name')->label('Attributes')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('on_backorder')->label('On Backorder'),
                Tables\Filters\SelectFilter::make('status')->options(['Active' => 'Active', 'Inactive' => 'Inactive']),
                Tables\Filters\SelectFilter::make('macro_category')
                    ->label('Macro Category')
                    ->options(fn () => Product::whereNotNull('macro_category')
                        ->distinct()
                        ->orderBy('macro_category')
                        ->pluck('macro_category', 'macro_category')
                        ->all()),
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
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();
    }
}
