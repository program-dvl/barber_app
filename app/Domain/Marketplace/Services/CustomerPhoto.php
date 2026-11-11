<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Models\Customer;
use App\Support\Audit\AuditWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerPhoto
{
    public function save(Customer $customer, ?UploadedFile $photo): void
    {
        $bytes = $photo ? $this->raster($photo) : null;
        DB::transaction(function () use ($customer, $bytes) {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $path = $bytes === null ? null : 'marketplace/customers/'.$locked->public_id.'/'.hash('sha256', $bytes).'.jpg';
            if ($path === $locked->photo_path) {
                return;
            }
            if ($bytes !== null) {
                try {
                    $stored = Storage::disk('private')->put($path, $bytes);
                } catch (\Throwable $error) {
                    report($error);
                    $stored = false;
                }
                if (! $stored) {
                    throw ValidationException::withMessages(['photo' => 'Your photo could not be saved. Your current photo and profile details are kept. Contact support if this continues.']);
                }
            }
            $old = $locked->photo_path;
            $locked->forceFill(['photo_path' => $path])->save();
            app(AuditWriter::class)->write($path ? 'marketplace.customer.photo_saved' : 'marketplace.customer.photo_removed', target: $locked, before: ['has_photo' => (bool) $old], after: ['has_photo' => (bool) $path], metadata: ['marketplace_customer' => $locked->public_id], source: 'marketplace_customer');
            if ($old) {
                DB::afterCommit(fn () => Storage::disk('private')->delete($old));
            }
        }, 3);
        $customer->refresh();
    }

    private function raster(UploadedFile $file): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['photo' => 'Photo uploads are temporarily unavailable. Your current photo is kept. Please contact support.']);
        }
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || min($info[0], $info[1]) < 100 || max($info[0], $info[1]) > 6000 || $info[0] * $info[1] > 12000000) {
            throw ValidationException::withMessages(['photo' => 'Choose a JPEG, PNG or WebP photo at least 100 pixels wide and high, up to 12 megapixels.']);
        }
        $source = @imagecreatefromstring($file->get());
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'Choose another photo. This image could not be opened.']);
        }
        $orientation = $info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data') ? (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180, 5,6 => -90, 7,8 => 90, default => 0
        };
        if ($angle) {
            $rotated = imagerotate($source, $angle, 0);
            imagedestroy($source);
            $source = $rotated;
        }
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($source, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($source, IMG_FLIP_VERTICAL);
        }
        $side = min(imagesx($source), imagesy($source));
        $target = imagecreatetruecolor(256, 256);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, (int) ((imagesx($source) - $side) / 2), (int) ((imagesy($source) - $side) / 2), 256, 256, $side, $side);
        ob_start();
        imagejpeg($target, null, 88);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        return $bytes;
    }
}
