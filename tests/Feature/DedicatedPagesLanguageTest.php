<?php

namespace Tests\Feature;

use Hop\Ui\Locales;
use Tests\TestCase;

class DedicatedPagesLanguageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The tests run on APP_URL (link.hel.ink): a browser that has had its old hop_lang moved to .hel.ink once.
        // The move itself is FamilyPreferenceCookiesTest's.
        $this->withUnencryptedCookie('hop_pref', '2');
    }

    public function test_all_eight_language_catalogs_exist_and_have_identical_keys(): void
    {
        $languages = Locales::codes();
        $this->assertCount(9, $languages);
        $baseFile = base_path('lang/en.json');
        $this->assertFileExists($baseFile);

        $baseKeys = array_keys(json_decode(file_get_contents($baseFile), true));
        sort($baseKeys);

        foreach ($languages as $lang) {
            $path = base_path("lang/{$lang}.json");
            $this->assertFileExists($path, "Language catalog {$lang}.json missing");

            $data = json_decode(file_get_contents($path), true);
            $this->assertIsArray($data, "Language catalog {$lang}.json is not valid JSON");

            $keys = array_keys($data);
            sort($keys);

            $missing = array_diff($baseKeys, $keys);
            $extra = array_diff($keys, $baseKeys);

            $this->assertEmpty($missing, "Catalog {$lang}.json is missing keys: ".implode(', ', $missing));
            $this->assertEmpty($extra, "Catalog {$lang}.json has extra keys: ".implode(', ', $extra));
        }
    }

    public function test_long_texts_are_translated_in_every_language(): void
    {
        $en = array_keys(json_decode(file_get_contents(base_path('lang/en.json')), true));
        foreach (Locales::codes() as $lang) {
            if ($lang === 'en') {
                continue;
            }
            $data = json_decode(file_get_contents(base_path("lang/{$lang}.json")), true);
            $left = [];
            foreach ($en as $key) {
                // Prose of five words or more must not be left in English; short labels and names may be the same word.
                if (str_word_count(strip_tags($key)) >= 5 && ($data[$key] ?? $key) === $key) {
                    $left[] = $key;
                }
            }
            $this->assertEmpty($left, "Catalog {$lang}.json still has English prose: ".implode(' | ', array_slice($left, 0, 5)));
        }
    }

    public function test_default_locale_is_english_and_ignores_accept_language_header(): void
    {
        $response = $this->withHeader('Accept-Language', 'fr,fr-FR;q=0.9,id;q=0.8')
            ->get('/about');

        $response->assertOk();
        $response->assertSee('Short links with analytics and QR codes.');
        $response->assertDontSee('Liens courts avec statistiques');
    }

    public function test_query_param_sets_locale_and_hop_lang_cookie(): void
    {
        $response = $this->get('/about?lang=id');

        $response->assertOk();
        $response->assertCookie('hop_lang', 'id', false);
        $response->assertSee('Tentang');
        $response->assertSee('Kembali ke LinkHop');
    }

    public function test_a_retired_or_unknown_language_falls_back_to_english_and_sets_no_cookie(): void
    {
        foreach (['ar', 'xx', 'zh-TW'] as $code) {
            $response = $this->get('/about?lang='.$code);

            $response->assertOk();
            $response->assertCookieMissing('hop_lang');
            $response->assertSee('lang="en"', false);
            $response->assertSee('dir="ltr"', false);
        }
    }

    public function test_old_short_codes_mean_the_new_ones(): void
    {
        $this->get('/about?lang=zh')->assertOk()->assertCookie('hop_lang', 'zh-CN', false)->assertSee('lang="zh-CN"', false);
        $this->get('/about?lang=pt')->assertOk()->assertCookie('hop_lang', 'pt-BR', false)->assertSee('lang="pt-BR"', false);
        $this->withUnencryptedCookie('hop_lang', 'zh')->get('/about')->assertOk()->assertSee('lang="zh-CN"', false);
    }

    public function test_german_and_russian_pages_render_with_their_language_codes(): void
    {
        foreach (['de', 'ru'] as $code) {
            $this->get('/about?lang='.$code)->assertOk()->assertCookie('hop_lang', $code, false)->assertSee('lang="'.$code.'"', false);
        }
    }

    public function test_cookie_persists_locale_selection(): void
    {
        $response = $this->withUnencryptedCookie('hop_lang', 'es')
            ->get('/features');

        $response->assertOk();
        $response->assertSee('Características');
        $response->assertSee('Volver a LinkHop');
    }

    public function test_unknown_locale_falls_back_to_default_english(): void
    {
        $response = $this->get('/about?lang=xx');

        $response->assertOk();
        $response->assertSee('About');
        $response->assertSee('Back to LinkHop');
    }
}
