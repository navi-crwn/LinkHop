<?php

namespace Tests\Feature;

use App\Http\Controllers\ChangelogController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_changelog_history_and_pagination(): void
    {
        $history = json_decode(file_get_contents(resource_path('changelog.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['LinkHop'], array_keys($history['sources']));
        $previous = PHP_INT_MAX;
        $urls = [];
        foreach ($history['entries'] as $entry) {
            $date = strtotime($entry['date']);
            $this->assertNotFalse($date);
            $this->assertLessThanOrEqual($previous, $date);
            $this->assertSame('LinkHop', $entry['repo']);
            $this->assertStringStartsWith('https://github.com/navi-crwn/LinkHop/', $entry['url']);
            $this->assertArrayNotHasKey($entry['url'], $urls);
            $urls[$entry['url']] = true;
            $previous = $date;
        }
        $controller = new ChangelogController;
        $first = $controller(Request::create('/changelog'))->getData();
        $this->assertGreaterThan(40, $first['total']);
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
}
