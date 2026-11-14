<?php

namespace Tests\Feature;

use App\Http\Controllers\ChangelogController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    /** What the page must never show: branch names, merge lines and the word the owner banned. */
    private const FORBIDDEN = '/\btrain\b|kereta|merge pull request|remote-tracking|\b(fix|feat|docs|chore|release|hotfix|refactor)\//i';

    public function test_changelog_history_and_pagination(): void
    {
        $history = json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $history['schema']);
        $this->assertSame(['LinkHop'], array_keys($history['sources']));
        $previous = PHP_INT_MAX;
        $urls = [];
        foreach ($history['entries'] as $entry) {
            $date = strtotime($entry['date']);
            $this->assertNotFalse($date);
            $this->assertLessThanOrEqual($previous, $date);
            $this->assertSame('LinkHop', $entry['repo']);
            $this->assertSame('tag', $entry['kind']);
            $this->assertStringStartsWith('https://github.com/navi-crwn/LinkHop/', $entry['url']);
            $this->assertArrayNotHasKey($entry['url'], $urls);
            $urls[$entry['url']] = true;
            $previous = $date;
        }
        $controller = new ChangelogController;
        $first = $controller(Request::create('/changelog'))->getData();
        $this->assertSame(count($history['entries']), $first['total']);
        $count = 0;
        for ($page = 1; $page <= $first['pages']; $page++) {
            $data = $controller(Request::create('/changelog', 'GET', ['page' => $page]))->getData();
            $count += $data['entries']->count();
            $this->assertLessThanOrEqual(40, $data['entries']->count());
        }
        $this->assertSame(count($history['entries']), $count);
        $last = $controller(Request::create('/changelog?page=999999'))->getData();
        $this->assertSame($last['pages'], $last['page']);
        $negative = $controller(Request::create('/changelog?page=-1'))->getData();
        $this->assertSame(1, $negative['page']);
        $this->assertSame(ChangelogController::class, app('router')->getRoutes()->match(Request::create('/changelog'))->getActionName());
    }

    public function test_every_release_is_written_for_readers(): void
    {
        $history = json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($history['entries']);
        foreach ($history['entries'] as $entry) {
            $label = $entry['id'];
            $this->assertNotSame('', trim($entry['title']), "$label has no title");
            $this->assertNotSame('', trim($entry['version']), "$label has no version");
            $this->assertNotEmpty($entry['items'], "$label has no plain-language items");
            foreach ($entry['items'] as $item) {
                $this->assertContains($item['type'], ['added', 'changed', 'fixed', 'security', 'removed', 'deprecated'], $label);
                $this->assertNotSame('', trim($item['where']), "$label has an item without a place");
                $this->assertNotSame('', trim($item['text']), "$label has an empty item");
            }
        }
    }

    public function test_the_page_shows_plain_releases_and_no_banned_words(): void
    {
        $html = $this->get('/changelog')->assertOk()->assertSee('QR codes page')->assertSee('Technical details')->getContent();
        // the list of releases (the rest of the page is the site's own frame)
        preg_match('~<ol class="space-y-4".*?</ol>~s', $html, $list);
        $this->assertNotEmpty($list, 'the release list is on the page');
        $this->assertSame(0, preg_match(self::FORBIDDEN, strip_tags($list[0])), 'a banned word is on the page');
        $this->assertSame(0, preg_match(self::FORBIDDEN, (string) file_get_contents(resource_path('changelog.json'))), 'a banned word is in the data');
        $this->assertSame(0, preg_match(self::FORBIDDEN, (string) file_get_contents(base_path('CHANGELOG.md'))), 'a banned word is in CHANGELOG.md');
    }

    public function test_the_labels_follow_the_language(): void
    {
        $this->withUnencryptedCookie('hop_lang', 'de')->get('/changelog')->assertOk()
            ->assertSee('Technische Details')->assertSee('Behoben')
            ->assertSee('Was sich in jeder HOP-App geändert hat, die neuesten zuerst.');
    }

    public function test_the_committed_snapshot_is_what_the_builder_makes(): void
    {
        $python = trim((string) shell_exec('command -v python3 || command -v python'));
        if ($python === '') {
            // the test image has no python; the changelog workflow runs the same check in CI
            $this->markTestSkipped('python is needed to check that resources/changelog.json is current');
        }
        exec(escapeshellarg($python).' '.escapeshellarg(base_path('scripts/build-changelog.py')).' --repo LinkHop --check 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function test_no_public_page_carries_a_banned_word_in_its_html(): void
    {
        foreach (['/', '/about', '/changelog', '/qr', '/check', '/faq'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(0, preg_match(self::FORBIDDEN, $html), "$path carries a banned word in its HTML");
        }
    }
}
