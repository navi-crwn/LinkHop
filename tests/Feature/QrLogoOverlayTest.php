<?php

use App\Services\LinkService;
use App\Support\QrPng;
use Hop\Safety\UrlGuard;
use Illuminate\Support\Facades\Http;

/** A guard that resolves from a fixed table. */
function logoGuard(array $answers): UrlGuard
{
    return new class($answers) extends UrlGuard
    {
        public function __construct(private array $answers) {}

        protected function resolveHost(string $host): array
        {
            return $this->answers[$host] ?? [];
        }
    };
}

function redLogo(int $side = 40): string
{
    $image = imagecreatetruecolor($side, $side);
    imagefilledrectangle($image, 0, 0, $side, $side, imagecolorallocate($image, 255, 0, 0));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

test('a QR logo is fetched through the guard, pinned to the checked address, and drawn on the code', function () {
    $seen = new ArrayObject;
    Http::fake(function ($request, $options) use ($seen) {
        $seen[] = $options['curl'][CURLOPT_RESOLVE] ?? null;

        return Http::response(redLogo(), 200, ['Content-Type' => 'image/png']);
    });
    app()->instance(UrlGuard::class, logoGuard(['logo.example' => ['93.184.216.34']]));
    $qr = QrPng::render('https://example.com', 512);

    $out = app(LinkService::class)->overlayLogo($qr, 'https://logo.example/logo.png');

    expect($out)->not->toBe($qr);
    $image = imagecreatefromstring($out);
    imagepalettetotruecolor($image); // with imagick loaded the PNG is indexed, and imagecolorat then returns a palette index
    expect(imagecolorat($image, 256, 256) & 0xFFFFFF)->toBe(0xFF0000);
    expect($seen[0])->toBe(['logo.example:443:93.184.216.34']);
});

test('a QR logo on an internal address is never fetched', function () {
    Http::fake();
    $qr = QrPng::render('https://example.com', 512);

    expect(app(LinkService::class)->overlayLogo($qr, 'http://127.0.0.1/logo.png'))->toBe($qr);
    Http::assertNothingSent();
});

test('a QR logo that is too large is left out', function () {
    Http::fake(fn () => Http::response(str_repeat('x', QrPng::MAX_LOGO_BYTES + 1024), 200));
    app()->instance(UrlGuard::class, logoGuard(['logo.example' => ['93.184.216.34']]));
    $qr = QrPng::render('https://example.com', 512);

    expect(app(LinkService::class)->overlayLogo($qr, 'https://logo.example/huge.png'))->toBe($qr);
});
