<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SponsorLogoResource\Pages;
use App\Models\SponsorLogo;
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
                ->required(),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\FileUpload::make('file')
                ->label('Logo Image')
                ->image()
                ->directory('sponsor-logos')
                ->imageResizeMode('cover')
                ->imageCropAspectRatio('16:9'),
            Forms\Components\Select::make('embellishment_position_id')
                ->label('Position')
                ->relationship('position', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('price')
                ->required()->numeric()->default(0)->prefix('$'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('file')->label('Logo')->circular(false),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('position.name')->label('Position')->badge(),
                Tables\Columns\TextColumn::make('price')->money('CAD')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            'index'  => Pages\ListSponsorLogos::route('/'),
            'create' => Pages\CreateSponsorLogo::route('/create'),
            'edit'   => Pages\EditSponsorLogo::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();
    }
}
