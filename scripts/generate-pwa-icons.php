<?php

// ponytail: one-off generator, not app code — run manually if icon needs regenerating, not wired into any build step.

function makeIcon(int $size, string $path, bool $maskableSafeZone): void
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $navy = imagecolorallocate($im, 4, 44, 108); // #042c6c
    imagefill($im, 0, 0, $navy);

    $white = imagecolorallocate($im, 255, 255, 255);
    $font = 'C:/Windows/Fonts/arialbd.ttf';
    $letter = 'S';

    // Maskable icons get cropped to a circle by the OS — keep the glyph inside the ~80% safe zone.
    $fontSize = $maskableSafeZone ? $size * 0.34 : $size * 0.44;

    if (is_file($font)) {
        $box = imagettfbbox($fontSize, 0, $font, $letter);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = (int) (($size - $textWidth) / 2 - $box[0]);
        $y = (int) (($size - $textHeight) / 2 - $box[7]);
        imagettftext($im, $fontSize, 0, $x, $y, $white, $font, $letter);
    } else {
        // Fallback kalau font TTF tidak tersedia di server: built-in bitmap font GD (font 5, max ukuran tetap).
        $fw = imagefontwidth(5);
        $fh = imagefontheight(5);
        imagestring($im, 5, (int) (($size - $fw) / 2), (int) (($size - $fh) / 2), $letter, $white);
    }

    imagepng($im, $path);
    imagedestroy($im);
}

$dir = __DIR__ . '/../public/pwa/icons';
makeIcon(192, "$dir/icon-192.png", false);
makeIcon(512, "$dir/icon-512.png", false);
makeIcon(512, "$dir/icon-maskable-512.png", true);

echo "done\n";
