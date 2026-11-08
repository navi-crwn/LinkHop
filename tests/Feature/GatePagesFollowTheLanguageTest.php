<?php

use App\Models\Link;
use App\Models\User;
use Hop\Bio\Models\BioPage;
use Illuminate\Support\Facades\Hash;

/*
| The pages a visitor can land on before anything else (report form, password and age gates) follow the visitor's language,
| the html lang attribute included.
*/

function gpText(string $html): string
{
    $body = preg_replace('~<(script|style)\b.*?</\1>~si', '', $html);

    return trim((string) preg_replace('~\s+~', ' ', strip_tags((string) $body)));
}

function gpCatalog(string $lang): array
{
    return json_decode((string) file_get_contents(lang_path("{$lang}.json")), true);
}

$languages = ['de', 'ja', 'ru'];

it('shows the report form in the language of the visitor', function (string $lang) {
    $html = $this->get("/report-abuse?lang={$lang}")->assertOk()->getContent();
    $t = gpCatalog($lang);
    expect($html)->toContain('<html lang="'.$lang.'"')->toContain(e($t['Send report']))->toContain(e($t['What is wrong?']))->toContain(e($t['Page address']));
    expect(gpText($html))->not->toContain('Submit report')->not->toContain('Flag spam')->not->toContain('Back home')->not->toContain('Limit: 5 reports');
})->with($languages);

it('confirms a report in the language of the visitor', function () {
    $this->post('/report-abuse?lang=de', ['url' => 'https://example.com/x', 'email' => 'r@example.com', 'reason' => 'This one looks harmful to me.'])
        ->assertSessionHas('status', gpCatalog('de')['Thank you. Your report has been submitted.']);
});

it('shows the password page of a link in the language of the visitor', function (string $lang) {
    Link::create(['user_id' => User::factory()->create()->id, 'slug' => 'gatelink', 'target_url' => 'https://example.com/x', 'status' => 'active', 'password_hash' => Hash::make('secret123')]);
    $html = $this->get("/l/gatelink?lang={$lang}")->assertOk()->getContent();
    $t = gpCatalog($lang);
    expect($html)->toContain('<html lang="'.$lang.'"')->toContain(e($t['Password required']))->toContain(e($t['Continue']));
    expect(gpText($html))->not->toContain('password protected')->not->toContain('Unlock link');
})->with($languages);

it('shows the password page of a bio page in the language of the visitor', function (string $lang) {
    BioPage::create(['user_id' => User::factory()->create()->id, 'slug' => 'gatepage', 'title' => 'x', 'theme' => 'default', 'is_published' => true, 'password_enabled' => true, 'password' => Hash::make('secret123')]);
    $html = $this->get("/b/gatepage?lang={$lang}")->assertOk()->getContent();
    $t = gpCatalog($lang);
    expect($html)->toContain('<html lang="'.$lang.'"')->toContain(e($t['Password required']))->toContain(e($t['Enter the password to view this page.']));
    expect(gpText($html))->not->toContain('Password Protected')->not->toContain('Unlock Page')->not->toContain('Back to home');
})->with($languages)->group('bio');

it('shows the age gate of a bio page in the language of the visitor', function (string $lang) {
    BioPage::create(['user_id' => User::factory()->create()->id, 'slug' => 'adultpage', 'title' => 'x', 'theme' => 'default', 'is_published' => true, 'is_adult_content' => true]);
    $html = $this->get("/b/adultpage?lang={$lang}")->assertOk()->getContent();
    $t = gpCatalog($lang);
    expect($html)->toContain('<html lang="'.$lang.'"')->toContain(e($t['Age Verification Required']))->toContain(e($t['I am 18+ — Enter']));
    expect(gpText($html))->not->toContain('Leave Page')->not->toContain('intended for adults only');
})->with($languages)->group('bio');

it('keeps the forms working', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'gatelink', 'target_url' => 'https://example.com/x', 'status' => 'active', 'password_hash' => Hash::make('secret123')]);
    $html = $this->get('/l/gatelink')->getContent();
    expect($html)->toContain('name="password"');
    $this->post('/l/gatelink', ['password' => 'secret123'])->assertOk()->assertHeader('Refresh');
});
