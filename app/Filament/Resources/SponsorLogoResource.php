<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SponsorLogoResource\Pages;
use App\Models\SponsorLogo;
use App\Support\MediaPicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SponsorLogoResource extends Resource
{
    protected static ?string $model = SponsorLogo::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Sponsor Logo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('club_id')
                ->label('Club')
                ->relationship('club', 'name')
                ->searchable()
                ->preload()
                ->required()
                // Club users only ever add logos for their own club — lock
                // the field to it instead of offering every club.
                ->disabled(fn () => auth()->user()?->isClub())
                ->dehydrated()
                ->default(fn () => auth()->user()?->isClub() ? auth()->user()->club_id : null),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\FileUpload::make('file')
                ->label('Logo File')
                ->directory('sponsor-logos')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg'])
                ->rules(['mimes:png,jpg,jpeg'])
                ->maxSize(100)
                ->required()
                ->helperText('PNG or JPG only — max 100KB.')
                ->validationMessages([
                    'mimes' => 'Only PNG or JPG files are allowed.',
                    'max'   => 'The file must not be larger than 100KB.',
                ]),
            MediaPicker::make('file', 'Or choose the logo from the Media library'),
            Forms\Components\FileUpload::make('vector_file')
                ->label('Vector File')
                ->directory('sponsor-logos-vector')
                // Not ->image() — vector/PDF uploads aren't rasters Filament
                // can crop/preview, so this just accepts + stores the file;
                // acceptedFileTypes covers the browser-side picker/drag-drop
                // restriction, the mimes rule below re-checks server-side by
                // extension (more reliable than MIME-sniffing for EPS/AI,
                // which browsers report inconsistently).
                ->acceptedFileTypes([
                    'image/svg+xml',
                    'application/pdf',
                    'application/postscript',
                    'application/illustrator',
                    'image/x-eps',
                ])
                ->rules(['mimes:eps,svg,ai,pdf'])
                ->maxSize(2048)
                ->helperText('EPS, SVG, AI, or PDF — max 2MB.'),
            Forms\Components\Toggle::make('conversion_requested')
                ->label('No vector file - request Conversion')
                ->helperText('Turn this on if a vector file isn\'t available — we\'ll create one from the logo file before it can go on a garment.')
                ->columnSpanFull(),
            Forms\Components\Select::make('embellishment_position_id')
                ->label('Position')
                ->relationship('position', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('price')
                ->required()->numeric()->default(0)->prefix('$')
                // Clubs can add logos but not price them — admin sets the
                // price afterwards. Visible (not hidden) so a club can see
                // the price once it's been set.
                ->disabled(fn () => auth()->user()?->isClub())
                ->dehydrated()
                ->helperText(fn () => auth()->user()?->isClub()
                    ? 'Set by an admin after you add the logo.'
                    : null),
        ])->columns(2);
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
                Tables\Columns\ImageColumn::make('file')->label('Logo')->circular(false),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->searchable()->sortable()
                    ->visible(fn () => ! auth()->user()?->isClub()),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('vector_file')
                    ->label('Vector')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => filled($record->vector_file)),
                Tables\Columns\IconColumn::make('conversion_requested')
                    ->label('Needs Conversion')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('position.name')->label('Position')->badge(),
                Tables\Columns\TextColumn::make('price')->money('CAD')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('club')->relationship('club', 'name')
                    ->visible(fn () => ! auth()->user()?->isClub()),
                Tables\Filters\TernaryFilter::make('conversion_requested')
                    ->label('Needs Conversion'),
            ])
            ->actions([
                Tables\Actions\Action::make('viewLogo')
                    ->label('View logo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn ($record) => filled($record->file))
                    ->url(fn ($record) => \Illuminate\Support\Facades\Storage::disk('public')->url($record->file))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('downloadVector')
                    ->label('View vector')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn ($record) => filled($record->vector_file))
                    ->url(fn ($record) => \Illuminate\Support\Facades\Storage::disk('public')->url($record->vector_file))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSponsorLogos::route('/'),
            'create' => Pages\CreateSponsorLogo::route('/create'),
            'edit'   => Pages\EditSponsorLogo::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->can('manage_sponsor_logos') || $user?->isClub() || false;
    }
}
