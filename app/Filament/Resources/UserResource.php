<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context) => $context === 'create'),
                // Deliberately NOT ->relationship('roles', 'name') — role
                // assignment is security-sensitive, so it's synced
                // explicitly in the page classes (afterCreate/afterSave)
                // rather than trusting Filament's automatic toMany sync,
                // same reasoning as PersistsOrderGrid/PersistsPackageItems
                // elsewhere in this app.
                Forms\Components\Select::make('role_name')
                    ->label('Role')
                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name'))
                    ->required()
                    ->helperText('Add more roles from the Roles screen — new roles show up here automatically.')
                    ->reactive(),
                Forms\Components\Select::make('club_id')
                    ->label('Club')
                    ->relationship('club', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (Get $get) => in_array($get('role_name'), ['master_club', 'club_sub_user']))
                    ->helperText('Master Club has full access to all of this club\'s orders. Club Sub-User can only create/see the orders they create themselves.'),
                Forms\Components\Select::make('warehouse_id')
                    ->label('Default Warehouse')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Used by the Scan Stock page. When set, that user goes straight to scanning against this warehouse instead of being asked to confirm one first.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Role')->badge(),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')->label('Default Warehouse')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name'),
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_users') ?? false;
    }
}
