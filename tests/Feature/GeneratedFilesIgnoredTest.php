<?php

// Files the app writes at runtime must not show up as untracked changes on the server.
test('runtime files are ignored by git', function (string $path) {
    exec('git -C '.escapeshellarg(base_path()).' check-ignore -q '.escapeshellarg($path), $output, $status);

    expect($status)->toBe(0, "{$path} is not in .gitignore");
})->with(['e2e env file' => '.env.e2e', 'e2e database' => 'database/e2e.sqlite']);
