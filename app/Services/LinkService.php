<?php

namespace App\Services;

use App\Models\Link;
use App\Models\RetiredSlug;
use App\Support\QrPng;
use App\Support\ReservedSlugs;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class LinkService
{
    public function cacheKey(string $slug): string
    {
        return "slug:{$slug}";
    }

    public function forgetCache(Link $link): void
    {
        Cache::forget($this->cacheKey($link->slug));
    }

    public function forgetCacheBySlug(string $slug): void
    {
        Cache::forget($this->cacheKey($slug));
    }

    public function generateQr(Link $link): void
    {
        $path = "qrcodes/{$link->slug}.png";
        Storage::disk('public')->makeDirectory('qrcodes');
        $fgColor = $this->hexToRgb($link->qr_fg_color ?? '#000000');
        $bgColor = $this->hexToRgb($link->qr_bg_color ?? '#ffffff');
        $qr = QrPng::render($link->qr_url, 512, array_values($fgColor), array_values($bgColor), 2);
        if (filled($link->qr_logo_url)) {
            $qr = $this->overlayLogo($qr, $link->qr_logo_url);
        }
        Storage::disk('public')->put($path, $qr);
        $link->forceFill(['qr_code_path' => $path])->saveQuietly();
    }

    public function overlayLogo(string $qrImageData, string $logoUrl): string
    {
        try {
            $qrImage = imagecreatefromstring($qrImageData);
            if (! $qrImage) {
                return $qrImageData; // Return original if failed
            }
            // Checked and pinned in one step: the fetch goes to the addresses that passed UrlGuard, with no redirects.
            $logo = Http::timeout(5)->connectTimeout(3)
                ->withOptions(['allow_redirects' => false, 'stream' => true, 'curl' => PinnedFetch::curl($logoUrl)])
                ->get($logoUrl);
            if (! $logo->successful()) {
                return $qrImageData; // Return original if logo fetch failed
            }
            // Read at most one byte more than the limit, so a huge answer is never held in memory.
            $body = $logo->toPsrResponse()->getBody();
            $logoData = Utils::copyToString($body, QrPng::MAX_LOGO_BYTES + 1);
            $body->close();
            $info = $logoData === '' || strlen($logoData) > QrPng::MAX_LOGO_BYTES ? false : @getimagesizefromstring($logoData);
            if (! $info || $info[0] > QrPng::MAX_LOGO_SIDE || $info[1] > QrPng::MAX_LOGO_SIDE) {
                return $qrImageData; // Too large, empty or not an image
            }
            $logoImage = @imagecreatefromstring($logoData);
            if (! $logoImage) {
                return $qrImageData; // Return original if logo invalid
            }
            $qrWidth = imagesx($qrImage);
            $qrHeight = imagesy($qrImage);
            $logoWidth = imagesx($logoImage);
            $logoHeight = imagesy($logoImage);
            $logoQrWidth = $qrWidth * 0.35;
            $logoQrHeight = $logoQrWidth; // Keep square
            $fromWidth = ($qrWidth - $logoQrWidth) / 2;
            $fromHeight = ($qrHeight - $logoQrHeight) / 2;
            $white = imagecolorallocate($qrImage, 255, 255, 255);
            imagefilledrectangle(
                $qrImage,
                (int) $fromWidth - 10,
                (int) $fromHeight - 10,
                (int) ($fromWidth + $logoQrWidth) + 10,
                (int) ($fromHeight + $logoQrHeight) + 10,
                $white
            );
            imagecopyresampled(
                $qrImage,
                $logoImage,
                (int) $fromWidth,
                (int) $fromHeight,
                0,
                0,
                (int) $logoQrWidth,
                (int) $logoQrHeight,
                $logoWidth,
                $logoHeight
            );
            ob_start();
            imagepng($qrImage);
            $result = ob_get_clean();
            imagedestroy($qrImage);
            imagedestroy($logoImage);

            return $result ?: $qrImageData;
        } catch (\Exception $e) {
            return $qrImageData;
        }
    }

    /**
     * Create a shortlink for the given user
     */
    public function createShortlink($user, string $url, string $title = 'Bio Link', bool $isPublic = false): Link
    {
        // Generate a unique slug
        $slug = $this->generateUniqueSlug();
        // Create the link using correct field names
        $link = Link::create([
            'user_id' => $user->id,
            'target_url' => $url,
            'slug' => $slug,
            'status' => 'active',
            'custom_title' => $title,
        ]);

        return $link;
    }

    /**
     * Generate a unique slug for the shortlink
     */
    protected function generateUniqueSlug(int $length = 6): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $slug = '';
        do {
            $slug = '';
            for ($i = 0; $i < $length; $i++) {
                $slug .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (ReservedSlugs::isReserved($slug) || Link::where('slug', $slug)->exists() || RetiredSlug::isBlocked($slug));

        return $slug;
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
