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
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\FileUpload::make('file')
                ->label('Logo Image')
                ->image()
                ->directory('sponsor-logos')
                ->imageResizeMode('cover')
                ->imageCropAspectRatio('16:9'),
            Forms\Components\TagsInput::make('positions')
                ->label('Available Positions')
                ->suggestions([
                    'Front Chest Left', 'Front Chest Right', 'Back Top', 'Back Centre',
                    'Left Sleeve', 'Right Sleeve', 'Left Leg', 'Right Leg', 'Collar',
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('file')->label('Logo')->circular(false),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('positions')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->positions ?? []),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
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
