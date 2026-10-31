<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('link_clicks has an index on clicked_at for the 30-day analytics', function () {
    expect(Schema::hasIndex('link_clicks', 'link_clicks_clicked_at_index'))->toBeTrue();
});

test('the index migration can run again without failing', function () {
    $migration = require database_path('migrations/2026_10_30_000001_index_link_clicks_clicked_at.php');
    $migration->up();

    expect(Schema::hasIndex('link_clicks', 'link_clicks_clicked_at_index'))->toBeTrue();
});
