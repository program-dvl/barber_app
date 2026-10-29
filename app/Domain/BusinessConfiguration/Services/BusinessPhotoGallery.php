<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Models\BusinessPhoto;
use App\Domain\PlatformAccess\Models\Business;
use App\Support\Audit\AuditWriter;
use App\Support\Files\TenantFilePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessPhotoGallery
{
    public const LIMIT = 8;

    public function __construct(private readonly AuditWriter $audit) {}

    public function photos(Business $business): Collection
    {
        return BusinessPhoto::query()->forBusiness($business)->orderBy('position')->orderBy('id')->get();
    }

    public function revision(Business $business): string
    {
        return hash('sha256', json_encode($this->photos($business)->map(fn ($photo) => [$photo->public_id, $photo->caption])->all(), JSON_THROW_ON_ERROR));
    }

    public function presentation(Business $business, bool $privatePreview = false): array
    {
        $photos = $this->photos($business);

        return [
            'photos' => $photos->map(fn ($photo) => [
                'public_id' => $photo->public_id,
                'caption' => $photo->caption,
                'url' => $privatePreview
                    ? route('business.configuration.photos.media', [$business, $photo->public_id])
                    : route('public.booking.photos.media', [$business->booking_slug, $photo->public_id]),
            ])->values()->all(),
            'revision' => hash('sha256', json_encode($photos->map(fn ($photo) => [$photo->public_id, $photo->caption])->all(), JSON_THROW_ON_ERROR)),
            'limit' => self::LIMIT,
        ];
    }

    public function store(Business $business, UploadedFile $file, ?string $caption, string $revision, string $idempotencyKey): void
    {
        $caption = $this->caption($caption);
        $requestHash = hash('sha256', $file->get()."\0".$caption);
        $storedKey = null;
        try {
            DB::transaction(function () use ($business, $file, $caption, $revision, $idempotencyKey, $requestHash, &$storedKey): void {
                Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
                $existing = BusinessPhoto::withTrashed()->forBusiness($business)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if (! hash_equals($existing->request_hash, $requestHash)) {
                        throw ValidationException::withMessages(['photo' => 'Choose the photo again before uploading a different image.']);
                    }

                    return;
                }
                $this->assertRevision($business, $revision);
                if ($this->photos($business)->count() >= self::LIMIT) {
                    throw ValidationException::withMessages(['photo' => 'Your gallery holds up to eight photos. Remove one before adding another.']);
                }
                $contents = $this->normalizedImage($file);
                $id = (string) Str::ulid();
                $path = 'configuration/gallery/'.$id.'.jpg';
                $storedKey = TenantFilePath::private($business, $path);
                if (! Storage::disk('private')->put($storedKey, $contents)) {
                    throw ValidationException::withMessages(['photo' => 'Your photo could not be saved right now. Your other page details are saved. Try adding this photo later.']);
                }
                $photo = BusinessPhoto::query()->create([
                    'business_id' => $business->id, 'public_id' => $id, 'path' => $path,
                    'caption' => $caption, 'position' => $this->photos($business)->count(),
                    'idempotency_key' => $idempotencyKey, 'request_hash' => $requestHash,
                ]);
                $this->audit->write('configuration.gallery.photo_added', $business, target: $photo, after: ['public_id' => $id, 'caption' => $caption]);
            });
        } catch (\Throwable $exception) {
            if ($storedKey) {
                Storage::disk('private')->delete($storedKey);
            }
            throw $exception;
        }
    }

    public function update(Business $business, string $publicId, ?string $caption, int $position, string $revision): void
    {
        DB::transaction(function () use ($business, $publicId, $caption, $position, $revision): void {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $photos = $this->photos($business);
            $photo = $photos->firstWhere('public_id', $publicId);
            abort_unless($photo, 404);
            if ($position >= $photos->count()) {
                throw ValidationException::withMessages(['position' => 'Choose a position within your current gallery.']);
            }
            $caption = $this->caption($caption);
            if ($photo->caption === $caption && $photos->search(fn ($item) => $item->id === $photo->id) === $position) {
                return;
            }
            $this->assertRevision($business, $revision);
            $before = $photos->map(fn ($item) => [$item->public_id, $item->caption])->all();
            $photo->caption = $caption;
            $ordered = $photos->reject(fn ($item) => $item->id === $photo->id)->values()->all();
            array_splice($ordered, $position, 0, [$photo]);
            foreach ($ordered as $index => $item) {
                $item->position = $index;
                if ($item->isDirty()) {
                    $item->save();
                }
            }
            $this->audit->write('configuration.gallery.updated', $business, target: $photo, before: ['photos' => $before], after: ['photos' => array_map(fn ($item) => [$item->public_id, $item->caption], $ordered)]);
        });
    }

    public function remove(Business $business, string $publicId, string $revision): void
    {
        DB::transaction(function () use ($business, $publicId, $revision): void {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $photo = BusinessPhoto::withTrashed()->forBusiness($business)->where('public_id', $publicId)->firstOrFail();
            if ($photo->trashed()) {
                return;
            }
            $this->assertRevision($business, $revision);
            $photo->delete();
            foreach ($this->photos($business) as $index => $remaining) {
                if ($remaining->position !== $index) {
                    $remaining->update(['position' => $index]);
                }
            }
            $this->audit->write('configuration.gallery.photo_removed', $business, target: $photo, before: ['public_id' => $photo->public_id, 'caption' => $photo->caption]);
            DB::afterCommit(fn () => Storage::disk('private')->delete(TenantFilePath::private($business, $photo->path)));
        });
    }

    public function image(Business $business, string $publicId)
    {
        $photo = BusinessPhoto::query()->forBusiness($business)->where('public_id', $publicId)->firstOrFail();
        $key = TenantFilePath::private($business, $photo->path);
        abort_unless(Storage::disk('private')->exists($key), 404);

        return response(Storage::disk('private')->get($key), 200, [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    private function assertRevision(Business $business, string $revision): void
    {
        if (! hash_equals($this->revision($business), $revision)) {
            throw ValidationException::withMessages(['revision' => 'Your gallery changed in another window. Reload the photos before saving.']);
        }
    }

    private function caption(?string $caption): ?string
    {
        return filled($caption) ? trim($caption) : null;
    }

    private function normalizedImage(UploadedFile $file): string
    {
        $contents = $file->get();
        $size = @getimagesizefromstring($contents);
        if (! $size || ! in_array($size['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)
            || $size[0] < 160 || $size[1] < 160 || $size[0] > 6000 || $size[1] > 6000 || $size[0] * $size[1] > 12000000) {
            throw ValidationException::withMessages(['photo' => 'Choose a PNG, JPEG or WebP between 160 and 6,000 pixels on each side, up to 12 megapixels.']);
        }
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['photo' => 'Photos cannot be saved right now. Your other page details are saved; please try adding this photo later.']);
        }
        $source = @imagecreatefromstring($contents);
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'This image could not be read. Choose another PNG, JPEG or WebP.']);
        }
        // Preserve the orientation of phone photos while discarding EXIF/location metadata.
        if ($size['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            if ($orientation === 2) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            } elseif ($orientation === 4) {
                imageflip($source, IMG_FLIP_VERTICAL);
            }
            $rotation = match ($orientation) {
                3 => 180, 5, 6 => 270, 7, 8 => 90, default => 0
            };
            if ($rotation) {
                $rotated = imagerotate($source, $rotation, 0);
                imagedestroy($source);
                $source = $rotated;
            }
            if (in_array($orientation, [5, 7], true)) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            }
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 2560 / max($width, $height));
        $image = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $width, $height);
        ob_start();
        try {
            imagejpeg($image, null, 86);

            return ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($source);
            imagedestroy($image);
        }
    }
}
