<?php

namespace App\Imports;

use App\Filament\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Matches MediaExport's layout for a round-trip export -> edit -> import.
 * Columns: Media ID (optional), Name, File Name.
 *
 * Two jobs:
 *  - Rename existing library entries in bulk (row has a Media ID; Name
 *    and/or File Name are updated).
 *  - Register images that were copied straight into storage/app/public/media/
 *    by hand (row has no ID; File Name points at a file that's now on disk).
 *
 * A File Name is always resolved against the media/ folder — bare
 * "logo.png" and "media/logo.png" both work. A row whose file isn't on
 * disk is reported, not guessed at.
 */
class MediaImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<string> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            // +1 heading row, +1 for 1-based spreadsheet rows.
            $line = $i + 2;

            $id = trim((string) ($row['media_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $fileInput = trim((string) ($row['file_name'] ?? ''));
            $path = $this->resolvePath($fileInput);

            if ($id !== '') {
                $media = Media::find((int) $id);

                if (! $media) {
                    $this->errors[] = "Row {$line}: no media with ID {$id}.";

                    continue;
                }

                $changes = [];

                if ($name !== '') {
                    $changes['name'] = $name;
                }

                if ($fileInput !== '') {
                    if ($path === null) {
                        $this->errors[] = "Row {$line}: file \"{$fileInput}\" is not in the media folder — upload it first.";
                    } else {
                        $changes = array_merge($changes, ['file' => $path], $this->meta($path));
                    }
                }

                if ($changes !== []) {
                    $media->update($changes);
                    $this->imported++;
                }

                continue;
            }

            // No ID: this row registers a hand-placed file.
            if ($fileInput === '') {
                $this->errors[] = "Row {$line}: needs a Media ID (to update) or a File Name (to add).";

                continue;
            }

            if ($path === null) {
                $this->errors[] = "Row {$line}: file \"{$fileInput}\" is not in the media folder — copy it into storage/app/public/media/ first.";

                continue;
            }

            $media = Media::firstOrNew(['file' => $path]);
            $media->name = $name !== '' ? $name : pathinfo($path, PATHINFO_FILENAME);
            $media->fill($this->meta($path));
            $media->save();
            $this->imported++;
        }
    }

    /**
     * Normalise a "File Name" cell to a media/ path that actually exists on
     * the public disk, or null if there's no such file.
     */
    private function resolvePath(string $input): ?string
    {
        if ($input === '') {
            return null;
        }

        $base = basename(str_replace('\\', '/', $input));
        $path = MediaResource::DIRECTORY . '/' . $base;

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    /**
     * @return array{mime_type: ?string, size: ?int}
     */
    private function meta(string $path): array
    {
        return [
            'mime_type' => Storage::disk('public')->mimeType($path) ?: null,
            'size'      => Storage::disk('public')->size($path) ?: null,
        ];
    }
}
