<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogoStockResource\Pages;
use App\Models\Club;
use App\Models\LogoStock;
use App\Models\SponsorLogo;
use App\Support\MediaPicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LogoStockResource extends Resource
{
    protected static ?string $model = LogoStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Logos Stock';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'Logo Stock';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('club_id')
                ->label('Club')
                ->relationship('club', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                // Prefills the club's own logo (Club.logo, set on the Club
                // resource) as this row's image — only fires on an actual
                // selection change, so switching clubs on a new row (or
                // deliberately re-picking one on an existing row) refreshes
                // it, but simply opening an existing row for edit never
                // clobbers an image someone already uploaded for that
                // specific logo-stock line. The FileUpload below stays
                // fully editable, so this is just a starting point.
                //
                // The FileUpload's own state format is an array keyed by a
                // random UUID (see BaseFileUpload's afterStateHydrated) —
                // $set() writes straight into Livewire state without running
                // that hydration, so a raw string here left the "image"
                // field's state as a plain string. That crashed the very
                // next Livewire request with "foreach() argument must be of
                // type array|object, string given" (getUploadedFiles() loops
                // over the state), so the value must be pre-wrapped to match.
                //
                // Also auto-fills "position" to the next open sort-order
                // number for the selected club (existing max + 1) instead of
                // leaving it at 0, so newly added rows land at the end of
                // that club's list by default — same reactive-on-selection
                // behavior as the image prefill above.
                ->afterStateUpdated(function (Set $set, ?int $state) {
                    $logo = $state ? Club::find($state)?->logo : null;

                    $set('image', $logo ? [(string) Str::uuid() => $logo] : null);

                    $set('position', $state
                        ? ((int) (LogoStock::where('club_id', $state)->max('position') ?? 0) + 1)
                        : 0);
                }),
            Forms\Components\TextInput::make('barcode')
                ->required()
                ->maxLength(255)
                ->helperText('Same barcode on every logo-stock line for this club — scanning it looks up all of this club\'s logos.'),
            Forms\Components\Select::make('logo_type_id')
                ->label('Logo Type')
                ->relationship('logoType', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('logo_stock_type')
                ->label('Stock Type')
                ->options(['logo' => 'Logo', 'numbers' => 'Numbers', 'sponsor' => 'Sponsor'])
                ->required()
                ->default('logo'),
            Forms\Components\TextInput::make('logo_name')
                ->label('Logo Name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('width')
                ->label('Width')
                ->maxLength(255),
            Forms\Components\TextInput::make('height')
                ->label('Height')
                ->maxLength(255),
            Forms\Components\TextInput::make('location')
                ->label('Location (Box Number)')
                ->maxLength(255),
            Forms\Components\Select::make('warehouse_id')
                ->label('Warehouse')
                ->relationship('warehouse', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('qty')
                ->label('Quantity')
                ->required()
                ->numeric()
                ->default(0)
                ->minValue(0),
            Forms\Components\TextInput::make('position')
                ->label('Position (Sort Order)')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('Lower numbers show first. Auto-filled to the next open position for the selected club — adjust if needed.'),
            Forms\Components\Select::make('sponsor_logo_id')
                ->label('Or Use a Sponsor Logo')
                ->helperText('Some logo-stock lines are for a sponsor logo, not the club\'s own — pick one of this club\'s already-uploaded Sponsor Logos to reuse its image below instead of uploading a new file.')
                // Not a real column — purely a picker that fills in the
                // "image" field below, same idea as the club_id
                // afterStateUpdated already does for the club's own logo.
                ->dehydrated(false)
                ->options(fn (Get $get) => $get('club_id')
                    ? SponsorLogo::where('club_id', $get('club_id'))->orderBy('name')->pluck('name', 'id')
                    : [])
                ->disabled(fn (Get $get) => blank($get('club_id')))
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    $logo = $state ? SponsorLogo::find($state)?->file : null;

                    if ($logo) {
                        $set('image', [(string) Str::uuid() => $logo]);
                    }
                }),
            MediaPicker::make('image', 'Or Use an Image from the Media Library')
                ->helperText('Reuse an image already uploaded in the Media module — fills the Image field below.'),
            Forms\Components\FileUpload::make('image')
                ->label('Image')
                ->directory('logo-stock')
                ->image()
                ->maxSize(2048)
                ->helperText('Defaults to the selected club\'s logo, or the Sponsor Logo picked above — upload a different file here to override it for this logo stock line only.'),
            Forms\Components\TextInput::make('vector_file_link')
                ->label('Vector File Link')
                ->url()
                ->maxLength(255)
                ->helperText('Link to the vector source file (e.g. shared drive URL).'),
            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('Image')->circular(false),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('club.code')->label('Club Code')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('barcode')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('logoType.name')->label('Logo Type')->badge(),
                Tables\Columns\TextColumn::make('logo_stock_type')->label('Stock Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'numbers' => 'Numbers',
                        'sponsor' => 'Sponsor',
                        default   => 'Logo',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'numbers' => 'warning',
                        'sponsor' => 'info',
                        default   => 'success',
                    }),
                Tables\Columns\TextColumn::make('logo_name')->label('Logo Name')->searchable(),
                Tables\Columns\TextColumn::make('width')->label('Width')->toggleable(),
                Tables\Columns\TextColumn::make('height')->label('Height')->toggleable(),
                Tables\Columns\TextColumn::make('location')->label('Location')->toggleable(),
                Tables\Columns\TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                Tables\Columns\TextColumn::make('qty')->label('Qty')->numeric()->sortable()->alignRight()
                    ->color(fn ($record) => match (true) {
                        $record->qty === 0 => 'danger',
                        $record->qty <= 5  => 'warning',
                        default            => 'success',
                    }),
                Tables\Columns\TextColumn::make('position')->label('Position')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('notes')->label('Notes')->limit(40)->wrap()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('club')->relationship('club', 'name')->searchable(),
                Tables\Filters\SelectFilter::make('warehouse')->relationship('warehouse', 'name'),
                Tables\Filters\SelectFilter::make('logoType')->relationship('logoType', 'name')->label('Logo Type'),
                Tables\Filters\SelectFilter::make('logo_stock_type')->label('Stock Type')->options(['logo' => 'Logo', 'numbers' => 'Numbers', 'sponsor' => 'Sponsor']),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('position');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLogoStocks::route('/'),
            'create' => Pages\CreateLogoStock::route('/create'),
            'bulk'   => Pages\BulkLogoStock::route('/bulk'),
            'edit'   => Pages\EditLogoStock::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_logo_stock') ?? false;
    }
}
