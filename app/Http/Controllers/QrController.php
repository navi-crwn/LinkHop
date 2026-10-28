<?php

namespace App\Http\Controllers;

use App\Support\QrPng;
use Illuminate\Http\Request;

/**
 * GET /api/qr-generate?data=&size=&color=&bgcolor=&logo=: a QR code PNG for the
 * link creation modal and the bio editor (the share dialogs render it client side).
 */
class QrController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->input('data');
        $size = (int) $request->input('size', 300);
        $size = max(50, min($size, 1000)); // Clamp to prevent memory-exhaustion DoS
        $color = $request->input('color', '000000');
        $bgcolor = $request->input('bgcolor', 'ffffff');
        $logoData = $request->input('logo'); // Base64 logo image
        if (! $data) {
            return response('Missing data parameter', 400);
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', (string) $color) || ! preg_match('/^[0-9a-fA-F]{6}$/', (string) $bgcolor)) {
            return response('Invalid color parameter', 400);
        }
        if (strlen((string) $data) > 2048) {
            return response('Data too long', 400);
        }
        $rgb = fn (string $hex) => [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        try {
            // High error correction leaves room for the logo overlay.
            $qrString = QrPng::render((string) $data, $size, $rgb($color), $rgb($bgcolor));
        } catch (\Throwable $e) {
            report($e);

            return response('Error generating QR code', 500);
        }
        // Optional logo; anything too large or not an image is ignored (QrPng::logo).
        $logo = QrPng::logo(is_string($logoData) ? $logoData : null);
        $qrImage = $logo ? @imagecreatefromstring($qrString) : false;
        if (! $logo || ! $qrImage) {
            return response($qrString)->header('Content-Type', 'image/png');
        }
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);
        // Logo covers 20% of the code, on a white square with 10px padding.
        $logoSize = (int) ($qrWidth * 0.2);
        $logoX = (int) (($qrWidth - $logoSize) / 2);
        $logoY = (int) (($qrHeight - $logoSize) / 2);
        $bgSize = $logoSize + 20;
        $bgX = (int) (($qrWidth - $bgSize) / 2);
        $bgY = (int) (($qrHeight - $bgSize) / 2);
        imagefilledrectangle($qrImage, $bgX, $bgY, $bgX + $bgSize, $bgY + $bgSize, imagecolorallocate($qrImage, 255, 255, 255));
        imagecopyresampled($qrImage, $logo, $logoX, $logoY, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
        ob_start();
        imagepng($qrImage, null, 9);
        $finalQr = ob_get_clean();
        imagedestroy($qrImage);
        imagedestroy($logo);

        return response($finalQr)->header('Content-Type', 'image/png');
    }
}
