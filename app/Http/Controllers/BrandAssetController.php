<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the brand images from resources/brand, the one place they live (see its README).
 * Links are made with route('brand.asset', 'file') (or the brand.logo / brand.logo.dark /
 * brand.favicon shortcuts) and carry a version that changes with the files, so a new logo
 * shows up everywhere at once instead of hiding behind a week-long browser or CDN cache.
 */
class BrandAssetController extends Controller
{
    private const TYPES = ['png' => 'image/png', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];

    public static function dir(): string
    {
        return resource_path('brand');
    }

    /** Changes whenever any file in resources/brand changes; goes into the URLs. */
    public static function version(): string
    {
        static $v;
        if ($v === null) {
            $sig = '';
            foreach (glob(self::dir().'/*.{png,svg,ico}', GLOB_BRACE) ?: [] as $f) {
                $sig .= basename($f).filemtime($f).filesize($f);
            }
            $v = substr(md5($sig), 0, 8);
        }

        return $v;
    }

    /** /brand/{v}/{file}: cached for good, the URL changes when the file does. */
    public function show(string $v, string $file): BinaryFileResponse
    {
        return $this->serve($file, 'public, max-age=31536000, immutable');
    }

    /** /brand/{file}: old links (emails already sent, saved pages); cached only briefly. */
    public function legacy(string $file): BinaryFileResponse
    {
        return $this->serve($file, 'public, max-age=3600');
    }

    private function serve(string $file, string $cache): BinaryFileResponse
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $path = self::dir().'/'.basename($file);
        abort_unless(isset(self::TYPES[$ext]) && is_file($path), 404);

        return response()->file($path, ['Cache-Control' => $cache, 'Content-Type' => self::TYPES[$ext]]);
    }
}
