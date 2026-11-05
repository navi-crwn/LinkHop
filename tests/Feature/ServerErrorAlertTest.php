<?php

use App\Models\HopApp;
use App\Models\SeoSetting;
use App\Support\HopSignature;
use App\Support\ServerErrorAlert;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

/*
| A 500 tells the owner within minutes, at most once per kind of error per hour. The mail names the kind and the place,
| and nothing a visitor sent: the details stay in the server's log. hel.ink has one address. Any other install names its
| own in the settings Imperium shows, and sends nothing until it does.
*/

const ALERT_SECRET = 'visitor-value-a1b2c3';

/** @return array<int, Email> */
function alertsSent(): array
{
    return Mail::mailer()->getSymfonyTransport()->messages()->map(fn ($sent) => $sent->getOriginalMessage())->all();
}

function alertSetting(string $method, array $body = [])
{
    $json = $body ? json_encode($body) : '';
    $ts = (string) time();
    $nonce = HopSignature::b64(random_bytes(16));
    $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('imperium', $ts, $nonce, $method, '/api/hop/settings', $json), 'imperium-secret-that-is-long-enough-1234', true));

    return test()->call($method, '/api/hop/settings', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HOP_APP' => 'imperium', 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce,
        'HTTP_X_HOP_ACTOR' => '7', 'HTTP_X_HOP_SIGNATURE' => $sig,
    ], $json);
}

beforeEach(function () {
    Cache::store('file')->flush();
    Storage::fake('local');
    config(['app.env' => 'production', 'app.url' => 'https://link.hel.ink']);
    Route::get('/_boom', fn () => throw new RuntimeException('failed for '.ALERT_SECRET))->name('test.boom');
    Route::get('/_other', fn () => throw new LogicException('other'))->name('test.other');
    Route::get('/_gone', fn () => abort(503));
});

it('mails the kind and the place of a 500 to the owner', function () {
    $this->get('/_boom')->assertStatus(500);

    expect(alertsSent())->toHaveCount(1);
    $mail = alertsSent()[0];
    expect($mail->getTo())->toHaveCount(1)
        ->and($mail->getTo()[0]->getAddress())->toBe('admin@hel.ink')
        ->and($mail->getSubject())->toContain('RuntimeException')
        ->and($mail->getTextBody())->toContain('RuntimeException')
        ->toContain('tests/Feature/ServerErrorAlertTest.php:')
        ->toContain('test.boom');
});

it('leaves out everything a visitor sent', function () {
    $this->withHeaders(['User-Agent' => 'agent-'.ALERT_SECRET])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
        ->get('/_boom?q='.ALERT_SECRET)->assertStatus(500);

    $mail = alertsSent()[0];
    expect($mail->getSubject().$mail->getTextBody())
        ->not->toContain(ALERT_SECRET)
        ->not->toContain('203.0.113.77')
        ->not->toContain('failed for')
        ->not->toContain('/_boom')
        ->not->toContain('#0 ');
});

it('sends one mail per kind of error per hour, and counts the rest for the next one', function () {
    foreach (range(1, 4) as $i) {
        $this->get('/_boom')->assertStatus(500);
    }
    expect(alertsSent())->toHaveCount(1);

    $this->travel(61)->minutes();
    $this->get('/_boom')->assertStatus(500);

    expect(alertsSent())->toHaveCount(2)
        ->and(alertsSent()[1]->getTextBody())->toContain('3 more since the last mail');
});

it('mails another kind of error separately', function () {
    $this->get('/_boom')->assertStatus(500);
    $this->get('/_other')->assertStatus(500);

    expect(alertsSent())->toHaveCount(2);
});

it('sends nothing for answers that are not a 500', function () {
    $this->get('/_gone')->assertStatus(503);
    $this->get('/no-such-page-here/at/all')->assertStatus(404);

    expect(alertsSent())->toBe([]);
});

it('sends nothing outside production and staging', function () {
    config(['app.env' => 'local']);
    $this->get('/_boom')->assertStatus(500);

    expect(alertsSent())->toBe([]);
});

it('sends nothing from another install until it names an address, so it never mails hel.ink', function () {
    config(['app.url' => 'https://links.example.test']);
    $this->get('/_boom')->assertStatus(500);

    expect(alertsSent())->toBe([]);
});

it('offers the address as an editable setting, as Imperium renders it', function () {
    HopApp::factory()->imperium('imperium-secret-that-is-long-enough-1234')->create();
    config(['app.url' => 'https://links.example.test']);

    $row = collect(alertSetting('GET')->assertOk()->json('data'))->firstWhere('key', ServerErrorAlert::SETTING);
    expect($row)->toMatchArray(['type' => 'string', 'readonly' => false, 'value' => null]);

    alertSetting('PUT', [ServerErrorAlert::SETTING => 'owner@example.test'])->assertOk();
    $row = collect(alertSetting('GET')->json('data'))->firstWhere('key', ServerErrorAlert::SETTING);
    expect($row['value'])->toBe('owner@example.test');
});

it('mails the address from the settings, and only that one', function () {
    HopApp::factory()->imperium('imperium-secret-that-is-long-enough-1234')->create();
    config(['app.url' => 'https://links.example.test']);
    alertSetting('PUT', [ServerErrorAlert::SETTING => ' owner@example.test '])->assertOk();

    $this->get('/_boom')->assertStatus(500);

    expect(alertsSent())->toHaveCount(1)
        ->and(array_map(fn ($to) => $to->getAddress(), alertsSent()[0]->getTo()))->toBe(['owner@example.test']);
});

it('stops mailing when the setting is emptied', function () {
    HopApp::factory()->imperium('imperium-secret-that-is-long-enough-1234')->create();
    config(['app.url' => 'https://links.example.test']);
    alertSetting('PUT', [ServerErrorAlert::SETTING => 'owner@example.test'])->assertOk();
    alertSetting('PUT', [ServerErrorAlert::SETTING => ''])->assertOk();

    $this->get('/_boom')->assertStatus(500);

    expect(alertsSent())->toBe([]);
});

it('refuses a bad address and saves nothing else from that form', function () {
    HopApp::factory()->imperium('imperium-secret-that-is-long-enough-1234')->create();
    foreach (['not-an-address', "a@example.test\r\nBcc: b@example.test", ['a@example.test'], 5] as $bad) {
        alertSetting('PUT', [ServerErrorAlert::SETTING => $bad, 'seo.site_title' => 'Changed'])
            ->assertStatus(422)->assertJsonPath('message', ServerErrorAlert::SETTING.' must be one email address');
    }
    expect(SeoSetting::first()?->site_title)->not->toBe('Changed');
    expect(ServerErrorAlert::stored())->toBeNull();
});

it('says so when the address cannot be stored', function () {
    HopApp::factory()->imperium('imperium-secret-that-is-long-enough-1234')->create();
    Storage::shouldReceive('disk->put')->andReturn(false);

    alertSetting('PUT', [ServerErrorAlert::SETTING => 'owner@example.test'])->assertStatus(500)->assertJsonPath('error', 'not_stored');
});

it('waits with the mail until the visitor has the answer', function () {
    $kernel = app(Kernel::class);
    $request = Request::create('/_boom');
    $response = $kernel->handle($request);

    expect($response->getStatusCode())->toBe(500)->and(alertsSent())->toBe([]);

    $kernel->terminate($request, $response);
    expect(alertsSent())->toHaveCount(1);
});

it('still counts and mails when the database cache is gone', function () {
    config(['cache.default' => 'database']);
    Schema::dropIfExists('cache');
    $this->get('/_boom')->assertStatus(500);

    expect(alertsSent())->toHaveCount(1);
});

it('keeps the 500 page when the mail cannot be sent', function () {
    config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'no-such-transport']]);
    Mail::purge();

    $this->get('/_boom')->assertStatus(500);
});
