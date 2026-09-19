<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Perkecil & kompres gambar upload (GD) - sisi terpanjang dibatasi $maxSide, di-encode ulang dengan
 * kualitas $quality. Format dipertahankan (jpg/png/webp) supaya nama/ekstensi lampiran tetap wajar.
 * Selain 3 format itu (gif animasi, svg, dll) dilewati - return null, panggil pakai jalur simpan biasa.
 */
class ImageCompressor
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @return array{binary: string, extension: string}|null null = tidak dikompres (bukan gambar didukung / gagal / tidak lebih kecil). */
    public static function compress(UploadedFile $file, int $maxSide = 1600, int $quality = 80): ?array
    {
        $mime = $file->getMimeType();
        if (! isset(self::EXTENSIONS[$mime]) || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $info = @getimagesize($file->getRealPath());
        // Batasi piksel supaya decode GD tidak menghabiskan memori (foto ~40MP ke atas dilewati).
        if (! $info || $info[0] * $info[1] > 40_000_000) {
            return null;
        }

        // Decode GD ~4 byte/piksel (+ kanvas hasil resize): kalau sisa memori PHP tidak cukup, lewati
        // (simpan file asli) daripada bikin request mati kehabisan memori.
        $limit = self::memoryLimitBytes();
        if ($limit > 0 && ($info[0] * $info[1] * 5) > ($limit - memory_get_usage()) * 0.8) {
            return null;
        }

        [$width, $height] = $info;

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $source) {
            return null;
        }

        // Foto HP menyimpan rotasi di EXIF - terapkan dulu, karena hasil encode ulang membuang EXIF.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0 && ($rotated = imagerotate($source, $angle, 0))) {
                $source = $rotated;
                [$width, $height] = [imagesx($source), imagesy($source)];
            }
        }

        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime !== 'image/jpeg') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($canvas, null, $quality),
            'image/png' => imagepng($canvas, null, 9),
            'image/webp' => imagewebp($canvas, null, $quality),
        };
        $binary = (string) ob_get_clean();

        // Kalau hasilnya tidak lebih kecil (mis. PNG kecil yang sudah optimal), pakai file asli.
        if ($binary === '' || ($scale >= 1 && strlen($binary) >= $file->getSize())) {
            return null;
        }

        return ['binary' => $binary, 'extension' => self::EXTENSIONS[$mime]];
    }

    private static function memoryLimitBytes(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
