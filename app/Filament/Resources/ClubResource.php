<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClubResource\Pages;
use App\Models\Club;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Club Details')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('phone')->tel(),
                Forms\Components\TextInput::make('address'),
                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->required()
                    ->default('active'),
            ])->columns(2),

            Forms\Components\Section::make('Club Items')->schema([
                // Deliberately NOT ->relationship('products') — Filament's
                // Repeater relationship-save treats every row without an
                // already-hydrated related record as a brand new model to
                // *create* (see PackageResource for the same issue). State is
                // saved manually via PersistsClubItems instead.
                Forms\Components\Repeater::make('items')
                    ->label('Assigned Items')
                    ->helperText('Only parent products can be assigned. Only these products — at the prices set below — are offered when an order for this club uses the "Club Items" type.')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->required()
                            ->searchable()
                            // The catalog runs into the thousands of products —
                            // search remotely instead of ->preload()ing every
                            // option, which blows past the memory limit.
                            ->getSearchResultsUsing(fn (string $search) => \App\Models\Product::whereNull('parent_sku')
                                ->where('name', 'like', "%{$search}%")
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id'))
                            ->getOptionLabelUsing(fn ($value) => \App\Models\Product::find($value)?->name)
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('club_price')
                            ->label('Club Price')->numeric()->prefix('$'),
                        Forms\Components\TextInput::make('online_store_price')
                            ->label('Online Store Price')->numeric()->prefix('$'),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->addActionLabel('Add Item')
                    ->default([]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'active', 'danger' => 'inactive']),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Items')
                    ->counts('products')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive']),
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
            'index'  => Pages\ListClubs::route('/'),
            'create' => Pages\CreateClub::route('/create'),
            'edit'   => Pages\EditClub::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();
    }
}
