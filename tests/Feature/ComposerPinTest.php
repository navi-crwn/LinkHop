<?php

/*
| hop/ui and hop/auth come from their tagged releases: composer.json asks for the release line, and composer.lock
| holds a tag (not a branch) fetched over https, so a build without an SSH key can install it.
*/

it('pins hop/ui and hop/auth to tagged releases', function () {
    $json = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
    $locked = fn (string $name) => collect($lock['packages'])->firstWhere('name', $name);

    expect($json['require']['hop/ui'])->toBe('^0.4.0')
        ->and($json['require']['hop/auth'])->toBe('^0.2.0')
        ->and($locked('hop/ui')['version'])->toMatch('/^v0\.4\.\d+$/')
        ->and($locked('hop/auth')['version'])->toMatch('/^v0\.2\.\d+$/')
        ->and($locked('hop/ui')['source']['url'])->toStartWith('https://github.com/navi-crwn/')
        ->and($locked('hop/auth')['source']['url'])->toStartWith('https://github.com/navi-crwn/')
        ->and(array_column($lock['aliases'] ?? [], 'package'))->not->toContain('hop/ui', 'hop/auth');
});
