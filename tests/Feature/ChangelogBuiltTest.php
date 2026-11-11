<?php

use App\Http\Controllers\ChangelogController;

it('shows the history the deploy built, and falls back to the committed snapshot', function () {
    $built = storage_path('app/changelog.json');
    $backup = is_file($built) ? file_get_contents($built) : null;
    $entry = ['id' => 'x1', 'kind' => 'pr', 'title' => 'Built at deploy time', 'date' => '2026-11-11T00:00:00Z', 'url' => 'https://github.com/navi-crwn/LinkHop/pull/1', 'app' => 'LinkHop', 'repo' => 'LinkHop'];
    try {
        file_put_contents($built, json_encode(['schema' => 1, 'sources' => ['LinkHop' => 'abc'], 'entries' => [$entry]]));
        $this->get('/changelog')->assertOk()->assertSee('Built at deploy time');

        file_put_contents($built, '{not json');
        $this->get('/changelog')->assertOk()->assertDontSee('Built at deploy time');
        expect(ChangelogController::history()['entries'])->not->toBeEmpty();

        unlink($built);
        expect(ChangelogController::history()['sources'])->toHaveKey('LinkHop');
    } finally {
        $backup === null ? @unlink($built) : file_put_contents($built, $backup);
    }
});
