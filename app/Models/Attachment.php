<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file on a job or on a driver log (TASK-454).
 *
 * `location` is a path RELATIVE to the one private disk (`local`, rooted at
 * storage/app/private). It used to be written two ways -- relative by the
 * log page, absolute by the job page -- and read back as a raw filesystem
 * path, so one of the two always failed; and the log page wrote to a disk
 * named `private` that was never configured, so a driver's upload could not
 * succeed at all. Rows written before migration 2026_09_30_000004 are still
 * read correctly by storagePath() if that data step has not run.
 *
 * Files are stored as `{uuid}.{ext}` so two drivers uploading IMG_0001.jpg
 * do not overwrite each other; the name the user gave is kept in `file_name`.
 */
class Attachment extends Model
{
    use SoftDeletes;

    public const DISK = 'local';

    /** Photos of paperwork and receipts, and PDFs. Nothing executable. */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'pdf'];

    public const MAX_KILOBYTES = 10240;

    public $fillable = [
        'attachable_id',
        'attachable_type',
        'location',
        'file_name',
        'is_public',
        'organization_id',
        'deleted_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        // The file goes with the row, and only then: a soft-deleted row can
        // still be reached by a super user restoring it.
        static::forceDeleted(function (self $attachment): void {
            $attachment->deleteFile();
        });
    }

    /** The validation rules every upload form shares. */
    public static function uploadRules(): array
    {
        return [
            'required',
            'file',
            'max:' . self::MAX_KILOBYTES,
            'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
        ];
    }

    /**
     * Write an upload to the private disk and record it against a job or a
     * log. Files for a log live in its job's folder, as they always have.
     */
    public static function store(UploadedFile $file, Model $attachable, int $organizationId, bool $isPublic = false): self
    {
        $jobId = $attachable instanceof UserLog ? $attachable->job_id : $attachable->getKey();
        $directory = 'jobs/attachments_' . $jobId;

        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
        $name = (string) Str::uuid() . '.' . $extension;

        if ($file->storeAs($directory, $name, self::DISK) === false) {
            throw new RuntimeException('The file could not be written to storage.');
        }

        return static::create([
            'attachable_id' => $attachable->getKey(),
            'attachable_type' => $attachable::class,
            'location' => $directory . '/' . $name,
            'file_name' => $file->getClientOriginalName(),
            'organization_id' => $organizationId,
            'is_public' => $isPublic,
        ]);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The path on the private disk, whatever shape the row was written in:
     * relative already, or absolute under storage/app/private or storage/app
     * from the job page before TASK-454.
     */
    public function storagePath(): string
    {
        $location = str_replace('\\', '/', (string) $this->location);

        $bases = [static::disk()->path(''), storage_path('app/private'), storage_path('app')];

        foreach ($bases as $base) {
            $base = rtrim(str_replace('\\', '/', $base), '/') . '/';

            if (Str::startsWith($location, $base)) {
                return substr($location, strlen($base));
            }
        }

        return ltrim($location, '/');
    }

    public function fileExists(): bool
    {
        return static::disk()->exists($this->storagePath());
    }

    /** The name the user gave the file; the basename for rows that predate the column. */
    public function getFileNameAttribute($value): string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        return basename(str_replace('\\', '/', (string) $this->location));
    }

    public function download(): StreamedResponse
    {
        return static::disk()->download($this->storagePath(), $this->file_name);
    }

    /**
     * Remove the file from the disk. Rows written before uuid names could
     * share one file (two uploads of the same name in one job), so the file
     * stays while another row still points at it.
     */
    public function deleteFile(): static
    {
        $shared = static::withTrashed()
            ->where('location', $this->location)
            ->whereKeyNot($this->getKey())
            ->exists();

        if (! $shared) {
            static::disk()->delete($this->storagePath());
        }

        return $this;
    }
}
