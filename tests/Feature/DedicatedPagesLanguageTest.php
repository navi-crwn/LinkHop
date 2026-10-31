<?php

namespace Tests\Feature;

use Tests\TestCase;

class DedicatedPagesLanguageTest extends TestCase
{
    public function test_all_eight_language_catalogs_exist_and_have_identical_keys(): void
    {
        $languages = ['en', 'id', 'zh', 'es', 'pt', 'fr', 'ja', 'ar'];
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

    public function test_arabic_locale_sets_rtl_direction(): void
    {
        $response = $this->get('/about?lang=ar');

        $response->assertOk();
        $response->assertCookie('hop_lang', 'ar', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
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
