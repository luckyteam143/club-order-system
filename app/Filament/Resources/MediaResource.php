<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Media';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Media';
    protected static ?string $pluralLabel = 'Media Library';
    protected static ?string $recordTitleAttribute = 'name';

    /** Every media file lives under this folder on the `public` disk. */
    public const DIRECTORY = 'media';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('What you\'ll search for when picking this image elsewhere.'),
            // Plain <img> preview of the stored file — always renders (even
            // cross-origin), so the current image is visible on Edit
            // regardless of the FileUpload widget's own preview, and links
            // out to the full-size original.
            Forms\Components\Placeholder::make('current_image')
                ->label('Current image')
                ->visible(fn (?Media $record) => filled($record?->file))
                ->content(fn (?Media $record) => new HtmlString(
                    '<a href="' . e($record->url) . '" target="_blank" rel="noopener">'
                    . '<img src="' . e($record->url) . '" alt="" loading="lazy" '
                    . 'style="max-height:180px;width:auto;border-radius:8px;background:#fff;'
                    . 'padding:4px;border:1px solid rgb(0 0 0 / 0.1);" /></a>'
                )),
            Forms\Components\FileUpload::make('file')
                ->label('Image')
                ->disk('public')
                ->directory(self::DIRECTORY)
                ->image()
                ->imageEditor()
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                ->maxSize(4096)
                ->required()
                ->helperText('PNG, JPG, WEBP or SVG — max 4MB.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $seesAllMedia = self::seesAllMedia();

        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\ImageColumn::make('thumb_path')
                    ->label('Image')
                    ->disk('public')
                    // Load the lightweight generated preview, not the
                    // full-size upload — keeps this listing fast. Falls
                    // back to the original for rows without a thumbnail.
                    ->getStateUsing(fn (Media $record) => $record->thumb_path ?: $record->file)
                    ->height(72)
                    ->square()
                    ->extraImgAttributes(['style' => 'object-fit:contain;background:#fff;border-radius:6px;padding:2px;'])
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                Tables\Columns\TextColumn::make('club.name')
                    ->label('Club')
                    ->placeholder('Global')
                    ->sortable()
                    ->visible($seesAllMedia),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Added by')
                    ->placeholder('—')
                    ->toggleable()
                    ->visible($seesAllMedia),
                Tables\Columns\TextColumn::make('mime_type')->label('Type')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state) => $state ? number_format($state / 1024, 0) . ' KB' : '—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('View original')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Media $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn (Media $record) => filled($record->file)),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    /**
     * A "media administrator": a non-club user holding view_media. They see
     * every club's media plus the global (club-less) entries, and get the
     * Import / Export tools. A club user with view_media is scoped to their
     * own club by Media::scopeVisibleTo().
     */
    public static function seesAllMedia(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('view_media') && ! $user->isClub());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_media') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_media') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('edit_media') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete_media') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('delete_media') ?? false;
    }

    /**
     * Stamp mime type + size from the file that's actually on disk. Shared
     * by the Create and Edit pages so both stay in sync after an upload or
     * a file swap.
     */
    public static function fillFileMeta(array $data): array
    {
        $path = $data['file'] ?? null;

        if ($path && Storage::disk('public')->exists($path)) {
            $data['mime_type'] = Storage::disk('public')->mimeType($path) ?: null;
            $data['size'] = Storage::disk('public')->size($path) ?: null;
        }

        return $data;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit'   => Pages\EditMedia::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_media') ?? false;
    }
}
