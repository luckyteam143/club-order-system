<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Support\MediaThumbnail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One entry in the shared image library. `file` is a path on the `public`
 * disk (under `media/…`); other resources store that same path string in
 * their own image column when a media file is picked, so nothing
 * downstream needs to know about this model.
 *
 * Ownership: an admin upload leaves club_id null (a global asset). A club
 * user's upload is stamped with their club, and club users only ever see
 * or pick media stamped with their own club — never each other's, never
 * the global ones.
 */
class Media extends Model
{
    use LogsActivity;

    protected $table = 'media';

    protected $fillable = ['name', 'file', 'thumb_path', 'mime_type', 'size', 'club_id', 'created_by'];

    protected $casts = ['size' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (Media $media) {
            $user = auth()->user();

            if ($user) {
                $media->created_by ??= $user->getKey();

                // Club users can only ever own media within their own club;
                // admins upload global (club_id stays null unless set).
                if ($media->club_id === null && $user->isClub()) {
                    $media->club_id = $user->club_id;
                }
            }
        });

        // Never leave a library entry without a name — fall back to the
        // uploaded file's own base name.
        static::saving(function (Media $media) {
            if (blank($media->name) && filled($media->file)) {
                $media->name = pathinfo($media->file, PATHINFO_FILENAME);
            }

            // (Re)build the listing thumbnail whenever the underlying file
            // changes, or when one was never generated. Covers uploads,
            // file swaps on Edit and the hand-placed files registered by
            // MediaImport alike.
            if (filled($media->file) && ($media->isDirty('file') || blank($media->thumb_path))) {
                if ($media->isDirty('file')) {
                    MediaThumbnail::forget($media->getOriginal('thumb_path'));
                }

                $media->thumb_path = MediaThumbnail::generate($media->file);
            }
        });

        // Don't leave orphaned thumbnails behind when a library entry goes.
        static::deleting(function (Media $media) {
            MediaThumbnail::forget($media->thumb_path);
        });
    }

    /**
     * Limits the query to what $user is allowed to see: everything for a
     * media manager, only their own club's entries for a club user,
     * nothing for anyone else.
     */
    public function scopeVisibleTo(Builder $query, ?\App\Models\User $user): Builder
    {
        if (! $user?->can('view_media')) {
            return $query->whereRaw('1 = 0');
        }

        // A non-club user with view_media is a media administrator: they
        // see every club's entries plus the global (club-less) ones. A
        // club user only ever sees their own club's.
        if ($user->isClub()) {
            return $query->where('club_id', $user->club_id);
        }

        return $query;
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getUrlAttribute(): ?string
    {
        return filled($this->file) ? Storage::disk('public')->url($this->file) : null;
    }

    /**
     * URL of the small listing preview, falling back to the full-size file
     * when no thumbnail has been generated (e.g. legacy rows, SVGs).
     */
    public function getThumbUrlAttribute(): ?string
    {
        $path = filled($this->thumb_path) ? $this->thumb_path : $this->file;

        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'file', 'mime_type', 'size', 'club_id', 'created_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('media')
            ->setDescriptionForEvent(fn (string $eventName) => "Media \"{$this->name}\" has been {$eventName}");
    }
}
