<?php

namespace App\Domain\BusinessConfiguration\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BrandImageNormalizer
{
    /** @return array{contents:string,extension:string} */
    public function normalize(UploadedFile $file, string $kind): array
    {
        $contents = $file->get();
        $size = @getimagesizefromstring($contents);
        $minimum = $kind === 'logo' ? 32 : 160;
        if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || min($size[0], $size[1]) < $minimum || max($size[0], $size[1]) > 6000 || $size[0] * $size[1] > 12000000) {
            throw ValidationException::withMessages(['asset' => 'Choose a PNG, JPEG or WebP between '.$minimum.' and 6,000 pixels on each side, up to 12 megapixels.']);
        }
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['asset' => 'Images cannot be saved right now. Your existing image is unchanged. Please contact support if this continues.']);
        }
        $source = @imagecreatefromstring($contents);
        if (! $source) {
            throw ValidationException::withMessages(['asset' => 'This image could not be opened. Choose another PNG, JPEG or WebP.']);
        }
        if ($size[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            $angle = match ($orientation) {
                3 => 180, 5, 6 => 270, 7, 8 => 90, default => 0
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
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, ($kind === 'logo' ? 1200 : 2560) / max($width, $height));
        $output = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
        if ($kind === 'logo') {
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
        } else {
            imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        }
        imagecopyresampled($output, $source, 0, 0, 0, 0, imagesx($output), imagesy($output), $width, $height);
        ob_start();
        try {
            $ok = $kind === 'logo' ? imagepng($output, null, 6) : imagejpeg($output, null, 86);
            $normalized = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($source);
            imagedestroy($output);
        }
        if (! $ok || ! is_string($normalized) || $normalized === '') {
            throw ValidationException::withMessages(['asset' => 'This image could not be prepared. Choose another image.']);
        }

        return ['contents' => $normalized, 'extension' => $kind === 'logo' ? 'png' : 'jpg'];
    }
}
