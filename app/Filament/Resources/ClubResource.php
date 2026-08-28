<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClubResource\Pages;
use App\Models\Club;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                Forms\Components\TextInput::make('code')
                    ->label('Club Code')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Used to match this club\'s barcode to its Logos Stock.'),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('phone')->tel(),
                Forms\Components\TextInput::make('address'),
                Forms\Components\TextInput::make('contact_person')
                    ->label('Contact Person')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->required()
                    ->default('active'),
                Forms\Components\FileUpload::make('logo')
                    ->label('Club Logo')
                    ->directory('club-logos')
                    ->image()
                    ->maxSize(100)
                    ->helperText('PNG, JPG, or SVG — max 100KB.')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Forecast Windows')
                ->description('Overrides the global default Summer/Winter Forecast submission dates (Settings > Forecasts) for this club specifically. Leave a pair blank to use the global default instead.')
                ->schema([
                    Forms\Components\DatePicker::make('summer_forecast_open_at')->label('Summer Opens'),
                    Forms\Components\DatePicker::make('summer_forecast_close_at')->label('Summer Closes'),
                    Forms\Components\DatePicker::make('winter_forecast_open_at')->label('Winter Opens'),
                    Forms\Components\DatePicker::make('winter_forecast_close_at')->label('Winter Closes'),
                ])
                ->columns(4),

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
                        Forms\Components\Toggle::make('has_club_crest')
                            ->label('Club Crest')
                            ->helperText('This item carries the club badge.')
                            ->default(true)
                            ->live(),
                        Forms\Components\TextInput::make('crest_number')
                            ->label('Crest #')
                            ->helperText('Which crest artwork (1, 2, 3…) — a club may use a different crest on the jersey vs. the shorts.')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->visible(fn (Get $get) => (bool) $get('has_club_crest')),
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
                Tables\Columns\ImageColumn::make('logo')->label('Logo')->circular(false)->toggleable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Club Code')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('contact_person')->label('Contact Person')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('notes')->label('Notes')->limit(40)->toggleable(isToggledHiddenByDefault: true),
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
        return auth()->user()?->can('manage_clubs') ?? false;
    }
}
