<?php

use App\Models\Link;
use App\Models\User;

function auditLink(): array
{
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'qrtest', 'target_url' => 'https://example.com/qr', 'status' => 'active']);

    return [$user, $link];
}

test('QR code downloads work as PNG, JPG and SVG without the imagick extension', function (string $format, string $mime) {
    [$user, $link] = auditLink();
    $response = $this->actingAs($user)->get(route('links.qr.download', [$link, $format]));
    $response->assertOk()->assertHeader('Content-Type', $mime);
    expect(strlen($response->getContent()))->toBeGreaterThan(200);
    if ($format !== 'svg') {
        expect(@imagecreatefromstring($response->getContent()))->not->toBeFalse();
    }
})->with([['png', 'image/png'], ['jpg', 'image/jpeg'], ['svg', 'image/svg+xml']]);

test('/l and /l/ send visitors to this install, not to a hard-coded host', function () {
    config(['app.url' => 'https://staging.example.test']);
    $this->get('/l?x=1')->assertRedirect('https://staging.example.test/?x=1');
    $this->get('/l/')->assertRedirect('https://staging.example.test/');
});

test('the /up health page is a plain answer without third-party scripts or fonts', function () {
    $this->get('/up')->assertOk()
        ->assertDontSee('cdn.jsdelivr.net')
        ->assertDontSee('fonts.bunny.net');
});

test('footer language links keep the current query string', function () {
    $html = $this->get('/changelog?page=2')->assertOk()->getContent();
    expect($html)->not->toContain('href="?lang=de"');
});

test('array-valued query parameters are a bad request, never a 500', function (string $url) {
    $user = User::factory()->create();
    expect($this->actingAs($user)->get($url)->getStatusCode())->toBeLessThan(500);
})->with(['/?lang[]=de', '/report-abuse?url[]=x', '/report-abuse?slug[]=x', '/check?url[]=x', '/api/qr-generate?data[]=x', '/api/qr-generate?data=x&size[]=1', '/login?next[]=x']);
