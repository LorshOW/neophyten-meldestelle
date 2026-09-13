<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * A photo or file on a Vorgang or an Inspection.
 *
 * Files are stored on a private disk. Never expose `path` directly - use
 * temporaryUrl(), which produces a signed, expiring route.
 */
class Attachment extends Model
{
    public const SOURCE_KOBO = 'kobo';
    public const SOURCE_AUSSENDIENST = 'aussendienst';
    public const SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'attachable_type', 'attachable_id', 'disk', 'path', 'original_filename',
        'mime', 'size', 'source', 'kobo_attachment_id', 'kobo_download_url',
        'checksum', 'uploaded_by_id', 'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'taken_at' => 'datetime',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('mime', 'like', 'image/%');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /**
     * Whether a browser can actually render this inline.
     *
     * Kobo accepts whatever the phone produced, and TIFF in particular is an
     * image that no browser displays - showing it in an <img> would just be a
     * broken tile.
     */
    public function isDisplayable(): bool
    {
        return in_array((string) $this->mime, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
        ], true);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /** Signed, expiring URL for viewing the file. */
    public function temporaryUrl(int $minutes = 30): string
    {
        return URL::temporarySignedRoute(
            'attachments.show',
            now()->addMinutes($minutes),
            ['attachment' => $this->getKey()],
        );
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes <= 0) {
            return '-';
        }

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
