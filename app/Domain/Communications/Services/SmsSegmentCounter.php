<?php

namespace App\Domain\Communications\Services;

class SmsSegmentCounter
{
    public function segments(string $text): int
    {
        $basic = '@£$¥èéùìòÇ'."\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ".'ÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
        $extensions = "\f^{}\\[~]|€";
        $length = 0;
        $unicode = false;
        foreach (mb_str_split($text) as $char) {
            if (mb_strpos($basic, $char) !== false) {
                $length++;
            } elseif (mb_strpos($extensions, $char) !== false) {
                $length += 2;
            } else {
                $unicode = true;
                break;
            }
        }
        if ($unicode) {
            $length = strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')) / 2;

            return max(1, (int) ceil($length / ($length <= 70 ? 70 : 67)));
        }

        return max(1, (int) ceil($length / ($length <= 160 ? 160 : 153)));
    }

    public function credits(string $text, string $destination, array $routes): ?int
    {
        uksort($routes, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($routes as $prefix => $multiplier) {
            if (str_starts_with($destination, $prefix) && is_int($multiplier) && $multiplier > 0) {
                return $this->segments($text) * $multiplier;
            }
        }

        return null;
    }
}
