<?php

namespace App\Support;

use App\Models\Media;
use Filament\Forms\Components\Select;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class MediaPicker
{
    /**
     * A "choose from the shared Media library" dropdown that fills an
     * existing FileUpload field ($targetField) with the picked image
     * instead of re-uploading it. Searchable by media name; each option
     * (and the chosen value) shows a thumbnail next to the name. Stores
     * nothing of its own (dehydrated(false)).
     *
     * Writes the FileUpload's `[uuid => path]` state shape rather than a
     * raw string — a plain string in a FileUpload's Livewire state crashes
     * the next request (see the same note in LogoStockResource). The path
     * lives on the `public` disk under `media/`, so the FileUpload,
     * ImageColumn and Storage::disk('public') helpers all keep working
     * unchanged wherever the value ends up stored.
     */
    public static function make(string $targetField, string $label = 'Or choose from Media library'): Select
    {
        return Select::make('media_library_pick_for_' . $targetField)
            ->label($label)
            ->helperText('Search images already uploaded in the Media module — fills the file above without re-uploading.')
            ->placeholder('Search the Media library by name…')
            ->searchable()
            ->allowHtml()
            ->dehydrated(false)
            ->live()
            ->options(fn () => self::asOptions(
                Media::query()->visibleTo(auth()->user())->orderBy('name')->limit(50)->get()
            ))
            ->getSearchResultsUsing(fn (string $search) => self::asOptions(
                Media::query()->visibleTo(auth()->user())
                    ->where('name', 'like', '%' . $search . '%')
                    ->orderBy('name')->limit(50)->get()
            ))
            ->getOptionLabelUsing(function ($value) {
                $media = Media::query()->visibleTo(auth()->user())->where('file', $value)->first();

                return $media ? self::optionHtml($media) : $value;
            })
            ->afterStateUpdated(function (Set $set, ?string $state) use ($targetField) {
                if (filled($state)) {
                    $set($targetField, [(string) Str::uuid() => $state]);
                }
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Media>  $media
     * @return array<string, string>  file path => "thumbnail + name" HTML
     */
    private static function asOptions($media): array
    {
        return $media->mapWithKeys(fn (Media $m) => [$m->file => self::optionHtml($m)])->all();
    }

    private static function optionHtml(Media $media): string
    {
        $name = e($media->name);
        $url = $media->url;

        $thumb = $url
            ? '<img src="' . e($url) . '" alt="" '
                . 'style="height:1.75rem;width:1.75rem;object-fit:contain;background:#fff;border-radius:4px;flex:none;" />'
            : '<span style="height:1.75rem;width:1.75rem;flex:none;border-radius:4px;background:#e5e7eb;"></span>';

        return '<span style="display:inline-flex;align-items:center;gap:.5rem;">' . $thumb . '<span>' . $name . '</span></span>';
    }
}
