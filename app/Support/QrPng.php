<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * PNG QR codes that work with or without the imagick extension.
 * simple-qrcode only writes PNG through imagick; servers with just GD
 * (the usual shared hosting setup) get the same image drawn with GD.
 */
class QrPng
{
    /** Largest logo accepted for the overlay, in bytes and pixels per side. */
    public const MAX_LOGO_BYTES = 512 * 1024;

    public const MAX_LOGO_SIDE = 2048;

    /**
     * @param  array{0:int,1:int,2:int}  $fg
     * @param  array{0:int,1:int,2:int}  $bg
     */
    public static function render(string $data, int $size, array $fg = [0, 0, 0], array $bg = [255, 255, 255], int $margin = 1): string
    {
        if (extension_loaded('imagick')) {
            return (string) QrCode::format('png')->size($size)->margin($margin)->errorCorrection('H')
                ->color(...$fg)->backgroundColor(...$bg)->generate($data);
        }

        return self::renderWithGd($data, $size, $fg, $bg, $margin);
    }

    private static function renderWithGd(string $data, int $size, array $fg, array $bg, int $margin): string
    {
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::H(), 'UTF-8')->getMatrix();
        $modules = $matrix->getWidth();
        $total = $modules + 2 * $margin;
        $scale = max(1, intdiv($size, $total));
        $offset = intdiv($size - $total * $scale, 2) + $margin * $scale;

        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$bg));
        $dark = imagecolorallocate($image, ...$fg);
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = $offset + $x * $scale;
                    $py = $offset + $y * $scale;
                    imagefilledrectangle($image, $px, $py, $px + $scale - 1, $py + $scale - 1, $dark);
                }
            }
        }
        ob_start();
        imagepng($image, null, 9);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * Decode a data:image/...;base64 logo only when it is small enough to be safe to
     * hand to GD (decoding a huge image allocates width * height * 4 bytes).
     */
    public static function logo(?string $dataUri): ?\GdImage
    {
        if (! $dataUri || ! preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/', $dataUri)) {
            return null;
        }
        $raw = substr($dataUri, strpos($dataUri, ',') + 1);
        if (strlen($raw) > (int) ceil(self::MAX_LOGO_BYTES * 4 / 3) + 4) {
            return null;
        }
        $bytes = base64_decode($raw, true);
        $info = $bytes ? @getimagesizefromstring($bytes) : false;
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_LOGO_SIDE || $info[1] > self::MAX_LOGO_SIDE) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);

        return $image ?: null;
    }
}
