<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use Filament\Forms;
use Filament\Forms\Form;
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
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->required()->numeric()->default(0)->prefix('$'),
                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->required()->default('active'),
            ])->columns(2),

            Forms\Components\Section::make('Products in Package')->schema([
                Forms\Components\Repeater::make('packageProducts')
                    ->label('Items')
                    ->relationship('products')
                    ->schema([
                        Forms\Components\Select::make('id')
                            ->label('Product')
                            ->helperText('Only parent products can be added to a package.')
                            ->options(\App\Models\Product::whereNull('parent_sku')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('pivot.qty')
                            ->label('Qty')->numeric()->default(1)->minValue(1),
                        Forms\Components\TextInput::make('pivot.per_item_price')
                            ->label('Price Override')->numeric()->prefix('$')->nullable(),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->addActionLabel('Add Product'),
            ]),
        ]);
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
