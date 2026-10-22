<?php

/**
 * Locally cached, resized WebP copies of the Pokémon artwork.
 *
 * Pages link to sprites/{size}/{id}.webp. If that file doesn't exist yet,
 * Apache hands the request to sprite.php, which builds and saves it; after
 * that Apache serves the file directly. scripts/warm_sprites.php builds them
 * all ahead of time.
 */
final class Sprite
{
    /** Longest edge in pixels for each size. */
    public const SIZES = [
        'small' => 128,
        'large' => 480,
    ];

    public const DIR = __DIR__ . '/../public/sprites';

    public static function url(int $id, string $size = 'small'): string
    {
        return 'sprites/' . $size . '/' . $id . '.webp';
    }

    public static function path(int $id, string $size): string
    {
        return self::DIR . '/' . $size . '/' . $id . '.webp';
    }

    /**
     * Downloads the original artwork, resizes it, saves it to disk and returns
     * the WebP bytes. Returns null if the image can't be fetched or converted.
     */
    public static function build(string $sourceUrl, int $id, string $size): ?string
    {
        if (!isset(self::SIZES[$size]) || !str_starts_with($sourceUrl, 'https://') || !function_exists('imagewebp')) {
            return null;
        }

        $context = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: pokevote-sprites/1.0\r\n"]]);
        $bytes = @file_get_contents($sourceUrl, false, $context);
        $original = $bytes !== false ? @imagecreatefromstring($bytes) : false;
        if (!$original) {
            return null;
        }

        // Scale down to fit the size, never up
        $max = self::SIZES[$size];
        $w = imagesx($original);
        $h = imagesy($original);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $original, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagewebp($resized, null, 82);
        $webp = (string) ob_get_clean();

        // Write then rename, so a reader never sees half a file
        $dir = dirname(self::path($id, $size));
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            $tmp = @tempnam($dir, '.tmp');
            if ($tmp !== false && @file_put_contents($tmp, $webp) !== false) {
                @chmod($tmp, 0644);
                @rename($tmp, self::path($id, $size));
            } elseif ($tmp !== false) {
                @unlink($tmp);
            }
        }

        return $webp;
    }
}
