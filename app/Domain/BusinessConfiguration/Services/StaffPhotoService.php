<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Files\TenantFilePath;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StaffPhotoService
{
    public function __construct(private readonly TenantContext $context, private readonly StaffWorkforceManager $profiles, private readonly AuditWriter $audit) {}

    public function authorize(Business $business, StaffProfile $staff, bool $write = true): void
    {
        abort_unless((int) $staff->business_id === (int) $business->id, 404);
        $actor = $this->context->membership();
        abort_unless($actor && (int) $actor->business_id === (int) $business->id, 403);
        $manage = $actor->hasPermissionTo(PermissionName::StaffManage->value, 'web');
        $all = $manage || $actor->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web');
        abort_unless($write ? $manage : ($all || ($actor->hasPermissionTo(PermissionName::CalendarViewOwn->value, 'web') && (int) $actor->staffProfile?->id === (int) $staff->id)), 403);
        $staff->loadMissing('locations');
        if (! $actor->hasRole('owner', 'web')) {
            $branches = $actor->locations()->pluck('locations.id');
            abort_unless($write ? $staff->locations->pluck('id')->diff($branches)->isEmpty() : $staff->locations->pluck('id')->intersect($branches)->isNotEmpty(), 403);
        }
    }

    public function save(Business $business, StaffProfile $staff, ?UploadedFile $photo, string $revision, User $actor): void
    {
        $contents = $photo ? $this->raster($photo) : null;
        DB::transaction(function () use ($business, $staff, $contents, $revision, $actor): void {
            Business::query()->lockForUpdate()->findOrFail($business->id);
            $locked = StaffProfile::query()->where('business_id', $business->id)->lockForUpdate()->findOrFail($staff->id);
            $locked->load('locations');
            $this->authorize($business, $locked);
            if (! hash_equals($this->profiles->profileRevision($locked), $revision)) {
                throw ValidationException::withMessages(['profile_revision' => 'This profile changed. Refresh the team member before changing their photo.']);
            }
            $path = $contents === null ? null : 'configuration/team/'.$locked->public_id.'/'.hash('sha256', $contents).'.jpg';
            if ($locked->photo_path === $path) {
                return;
            }
            if ($contents !== null) {
                try {
                    $stored = Storage::disk('private')->put(TenantFilePath::private($business, $path), $contents);
                } catch (\Throwable $error) {
                    report($error);
                    $stored = false;
                }
                if (! $stored) {
                    throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Your current photo is unchanged. Please try again.']);
                }
            }
            $before = filled($locked->photo_path);
            $locked->forceFill(['photo_path' => $path])->save();
            $this->audit->write($path ? 'staff.photo.updated' : 'staff.photo.removed', $business, $actor, $locked,
                $path ? 'Team member photo updated.' : 'Team member photo removed.', ['has_photo' => $before], ['has_photo' => filled($path)]);
        }, 3);
    }

    private function raster(UploadedFile $photo): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['photo' => 'Photo uploads are temporarily unavailable. Your existing photo is unchanged. Please contact support if this continues.']);
        }
        $contents = $photo->get();
        $info = @getimagesizefromstring($contents);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || min($info[0], $info[1]) < 100 || max($info[0], $info[1]) > 6000 || $info[0] * $info[1] > 24000000) {
            throw ValidationException::withMessages(['photo' => 'Choose a JPEG, PNG or WebP photo between 100 and 6,000 pixels, up to 24 megapixels.']);
        }
        $source = @imagecreatefromstring($contents);
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'This photo could not be opened. Choose another JPEG, PNG or WebP image.']);
        }
        $orientation = $info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data') ? (int) (@exif_read_data($photo->getRealPath())['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
        };
        if ($angle !== 0) {
            $rotated = imagerotate($source, $angle, 0);
            imagedestroy($source);
            $source = $rotated;
        }
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($source, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($source, IMG_FLIP_VERTICAL);
        }
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, 1200 / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        ob_start();
        imagejpeg($image, null, 88);
        $encoded = ob_get_clean();
        imagedestroy($image);
        imagedestroy($source);
        if (! is_string($encoded) || $encoded === '') {
            throw ValidationException::withMessages(['photo' => 'This photo could not be prepared. Choose another photo.']);
        }

        return $encoded;
    }

    public function version(StaffProfile $staff): ?string
    {
        return filled($staff->photo_path) ? substr(hash('sha256', $staff->photo_path), 0, 24) : null;
    }

    public function url(Business $business, StaffProfile $staff, bool $privatePreview = false): ?string
    {
        if (! $version = $this->version($staff)) {
            return null;
        }

        return $privatePreview
            ? route('business.configuration.booking-preview.staff-photo', [$business, $staff, $version])
            : route('public.booking.staff-photo', [$business->booking_slug, $staff->public_id, $version]);
    }

    public function teamUrl(Business $business, StaffProfile $staff): ?string
    {
        return ($version = $this->version($staff)) ? route('business.team.photo.show', [$business, $staff, $version]) : null;
    }
}
