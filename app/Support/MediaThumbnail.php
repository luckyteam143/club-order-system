<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a small WebP preview of a Media image on the `public` disk, used
 * by the Media Library listing so it never has to download full-size
 * uploads. SVGs are already tiny, so they just reuse their own path.
 *
 * All paths are relative to the `public` disk (the same shape as
 * Media::$file, e.g. "media/xxx.png").
 */
class MediaThumbnail
{
    /** Thumbnails live alongside the originals, in their own sub-folder. */
    public const DIRECTORY = 'media/thumbnails';

    /** Longest edge of the generated preview, in pixels. */
    public const MAX_EDGE = 240;

    /**
     * Generate (or regenerate) the thumbnail for $sourcePath and return
     * its path on the `public` disk. Returns the source path unchanged for
     * SVGs, and null if the source is missing or can't be decoded.
     */
    public static function generate(string $sourcePath): ?string
    {
        $disk = Storage::disk('public');

        if (! $sourcePath || ! $disk->exists($sourcePath)) {
            return null;
        }

        $mime = (string) ($disk->mimeType($sourcePath) ?: '');

        // GD can't rasterise vectors; the original SVG is small enough to
        // serve directly in the listing.
        if (Str::contains($mime, 'svg')) {
            return $sourcePath;
        }

        $bytes = $disk->get($sourcePath);
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($srcW, $srcH));
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $thumb = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        imagewebp($thumb, null, 80);
        $webp = (string) ob_get_clean();

        imagedestroy($image);
        imagedestroy($thumb);

        if ($webp === '') {
            return null;
        }

        $target = self::DIRECTORY . '/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '.webp';
        $disk->put($target, $webp);

        return $target;
    }

    /**
     * Delete a previously generated thumbnail. No-op when $thumbPath is
     * empty or points back at an original file (the SVG fallback).
     */
    public static function forget(?string $thumbPath): void
    {
        if ($thumbPath && Str::startsWith($thumbPath, self::DIRECTORY . '/')) {
            Storage::disk('public')->delete($thumbPath);
        }
    }
}
