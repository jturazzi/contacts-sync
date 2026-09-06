<?php

namespace App\Services\Contacts;

class AvatarGenerator
{
    protected const PALETTE = [
        [0, 120, 212],
        [16, 124, 16],
        [202, 80, 16],
        [196, 49, 75],
        [3, 131, 135],
        [135, 100, 184],
    ];

    protected const SIZE = 256;

    public static function generate(string $displayName): string
    {
        $size = self::SIZE;
        $initials = self::initials($displayName);
        [$r, $g, $b] = self::colorFor($displayName);

        $image = imagecreatetruecolor($size, $size);
        $background = imagecolorallocate($image, $r, $g, $b);
        imagefilledrectangle($image, 0, 0, $size, $size, $background);

        $white = imagecolorallocate($image, 255, 255, 255);
        $font = resource_path('fonts/DejaVuSans-Bold.ttf');
        $fontSize = (int) round($size * 0.36);

        $box = imagettfbbox($fontSize, 0, $font, $initials);
        $textWidth = abs($box[4] - $box[0]);
        $textHeight = abs($box[5] - $box[1]);
        $x = (int) round(($size - $textWidth) / 2);
        $y = (int) round(($size + $textHeight) / 2);

        imagettftext($image, $fontSize, 0, $x, $y, $white, $font, $initials);

        ob_start();
        imagejpeg($image, null, 90);

        return ob_get_clean();
    }

    protected static function initials(string $displayName): string
    {
        $parts = array_filter(preg_split('/\s+/', trim($displayName)) ?: []);
        $initials = collect($parts)
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    protected static function colorFor(string $seed): array
    {
        return self::PALETTE[crc32($seed) % count(self::PALETTE)];
    }
}
