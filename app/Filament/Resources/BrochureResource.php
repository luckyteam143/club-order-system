<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrochureResource\Pages;
use App\Models\Brochure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BrochureResource extends Resource
{
    protected static ?string $model = Brochure::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Brochure';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('club_id')
                ->label('Club')
                ->relationship('club', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('link')
                ->label('Brochure Link')
                ->url()
                ->required()
                ->maxLength(255),
            Forms\Components\DatePicker::make('created_date')
                ->label('Brochure Created Date')
                ->required(),
            Forms\Components\DatePicker::make('approved_date')
                ->label('Brochure Approved Date')
                ->helperText('Leave blank until the brochure has actually been approved.'),
            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('club.name')->label('Club')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('link')
                    ->label('Brochure Link')
                    ->url(fn (Brochure $record) => $record->link)
                    ->openUrlInNewTab()
                    ->limit(40),
                Tables\Columns\TextColumn::make('created_date')->label('Created')->date()->sortable(),
                Tables\Columns\TextColumn::make('approved_date')->label('Approved')->date()->sortable()->placeholder('Not yet approved'),
                Tables\Columns\IconColumn::make('approved_date')
                    ->label('Approved?')
                    ->getStateUsing(fn (Brochure $record) => filled($record->approved_date))
                    ->boolean(),
                Tables\Columns\TextColumn::make('notes')->label('Notes')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Added')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('club')->relationship('club', 'name')->searchable(),
                Tables\Filters\TernaryFilter::make('approved')
                    ->label('Approved')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('approved_date'),
                        false: fn ($query) => $query->whereNull('approved_date'),
                    ),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('created_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBrochures::route('/'),
            'create' => Pages\CreateBrochure::route('/create'),
            'edit'   => Pages\EditBrochure::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_brochures') ?? false;
    }
}
