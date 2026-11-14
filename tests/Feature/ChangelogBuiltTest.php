<?php

use App\Http\Controllers\ChangelogController;

it('shows the history the deploy built, and falls back to the committed snapshot', function () {
    $built = storage_path('app/changelog.json');
    $backup = is_file($built) ? file_get_contents($built) : null;
    $entry = ['id' => 'v9.9.9', 'kind' => 'tag', 'title' => 'Built at deploy time', 'date' => '2026-11-11T00:00:00Z', 'url' => 'https://github.com/navi-crwn/LinkHop/releases/tag/v9.9.9',
        'app' => 'LinkHop', 'repo' => 'LinkHop', 'version' => '9.9.9', 'summary' => '', 'items' => [['type' => 'fixed', 'where' => 'The built page', 'text' => 'Shown from the file the deploy wrote.']]];
    try {
        file_put_contents($built, json_encode(['schema' => 2, 'sources' => ['LinkHop' => '9.9.9'], 'entries' => [$entry]]));
        $this->get('/changelog')->assertOk()->assertSee('Built at deploy time')->assertSee('The built page')->assertSee('Shown from the file the deploy wrote.');

        file_put_contents($built, '{not json');
        $this->get('/changelog')->assertOk()->assertDontSee('Built at deploy time');
        expect(ChangelogController::history()['entries'])->not->toBeEmpty();

        unlink($built);
        expect(ChangelogController::history()['sources'])->toHaveKey('LinkHop');
    } finally {
        $backup === null ? @unlink($built) : file_put_contents($built, $backup);
    }
});

it('still shows a snapshot in the old form, next to the new one', function () {
    $built = storage_path('app/changelog.json');
    $backup = is_file($built) ? file_get_contents($built) : null;
    $old = ['id' => '#1', 'kind' => 'pr', 'title' => 'An old line', 'date' => '2026-11-10T00:00:00Z', 'url' => 'https://github.com/navi-crwn/LinkHop/pull/1', 'app' => 'LinkHop', 'repo' => 'LinkHop'];
    try {
        file_put_contents($built, json_encode(['schema' => 1, 'sources' => ['LinkHop' => 'abc123def456'], 'entries' => [$old]]));
        $this->get('/changelog')->assertOk()->assertSee('An old line')->assertSee('Merged pull request');
    } finally {
        $backup === null ? @unlink($built) : file_put_contents($built, $backup);
    }
});
