<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use DOMDocument;
use DOMXPath;
use Hop\Ui\Locales;
use Tests\TestCase;

/**
 * Every public page in the sitemap, in every language: the title, the meta description and the h1 must be in that
 * language (hreflang promises it), and the description must be long enough to be useful in a search result.
 */
class PublicPagesSeoLanguageTest extends TestCase
{
    /** Strings that are genuinely the same as English, and only in the languages where the real word is identical. */
    private const SAME_AS_ENGLISH = [
        'FAQ' => ['de', 'fr', 'ru'],
        'FAQ · LinkHop' => ['de', 'fr', 'ru'],
        'API' => ['de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru', 'zh-CN'],
        'API · LinkHop' => ['de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru', 'zh-CN'],
        'Status' => ['de', 'id', 'pt-BR'],
        'Status · LinkHop' => ['de', 'id', 'pt-BR'],
        'Contact' => ['fr'],
        'Contact · LinkHop' => ['fr'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withUnencryptedCookie('hop_pref', '2');
    }

    /** @return array{title:string, description:string, h1:string, blocks:list<string>} */
    private function read(string $path, string $lang): array
    {
        $html = $this->get('/'.$path.'?lang='.$lang)->assertOk()->getContent();
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xp = new DOMXPath($doc);
        $text = fn (string $q) => trim(preg_replace('/\s+/u', ' ', (string) $xp->evaluate("string({$q})")));
        $blocks = [];
        foreach ($xp->query('//main//p | //main//li[not(ancestor::ul[contains(@class,"info-family")])] | //main//h2 | //main//h3') as $node) {
            $blocks[] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
        }

        return [
            'title' => $text('//title'),
            'description' => $text('//meta[@name="description"]/@content'),
            'h1' => $text('(//h1)[1]'),
            'blocks' => $blocks,
        ];
    }

    public function test_titles_descriptions_and_headings_are_translated_on_every_public_page(): void
    {
        $left = [];
        foreach (SitemapController::PAGES as $path) {
            $en = $this->read($path, 'en');
            foreach (Locales::codes() as $lang) {
                if ($lang === 'en') {
                    continue;
                }
                $page = $this->read($path, $lang);
                foreach (['title', 'description', 'h1'] as $field) {
                    if ($page[$field] !== '' && $page[$field] === $en[$field] && ! in_array($lang, self::SAME_AS_ENGLISH[$en[$field]] ?? [], true)) {
                        $left[] = "/{$path}?lang={$lang} {$field}: {$en[$field]}";
                    }
                }
            }
        }
        $this->assertEmpty($left, "Still English:\n".implode("\n", $left));
    }

    public function test_body_prose_is_translated_on_every_public_page(): void
    {
        $left = [];
        // The changelog body lists release notes (English by design); the other pages are fixed text.
        foreach (array_diff(SitemapController::PAGES, ['changelog']) as $path) {
            $en = $this->read($path, 'en')['blocks'];
            foreach (Locales::codes() as $lang) {
                if ($lang === 'en') {
                    continue;
                }
                foreach ($this->read($path, $lang)['blocks'] as $block) {
                    if (str_word_count($block) >= 5 && ! str_starts_with($block, 'http') && in_array($block, $en, true)) {
                        $left[] = "/{$path}?lang={$lang}: ".mb_substr($block, 0, 70);
                    }
                }
            }
        }
        $this->assertEmpty($left, "Body still English:\n".implode("\n", array_unique($left)));
    }

    public function test_every_public_page_has_its_own_description_of_a_useful_length(): void
    {
        $bad = [];
        foreach (Locales::codes() as $lang) {
            $seen = [];
            foreach (SitemapController::PAGES as $path) {
                $d = $this->read($path, $lang)['description'];
                // CJK reads about twice as dense as Latin script, so its floor is lower.
                $min = in_array($lang, ['zh-CN', 'ja'], true) ? 25 : 70;
                $len = mb_strlen($d);
                if ($len < $min || $len > 155) {
                    $bad[] = "/{$path}?lang={$lang} has {$len} characters";
                }
                if (isset($seen[$d])) {
                    $bad[] = "/{$path}?lang={$lang} repeats the description of /{$seen[$d]}";
                }
                $seen[$d] = $path;
            }
        }
        $this->assertEmpty($bad, implode("\n", $bad));
    }
}
