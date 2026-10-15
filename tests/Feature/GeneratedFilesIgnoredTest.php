<?php

// Files the app writes at runtime must not show up as untracked changes on the server.
test('runtime files are ignored by git', function (string $path) {
    exec('git -C '.escapeshellarg(base_path()).' check-ignore -q '.escapeshellarg($path), $output, $status);

    expect($status)->toBe(0, "{$path} is not in .gitignore");
})->with([
    'disposable:update list' => fn () => str_replace(base_path().'/', '', config('disposable-email.storage')),
]);
