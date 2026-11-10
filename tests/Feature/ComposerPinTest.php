<?php

/*
| composer.json and composer.lock name the same commit of hop/ui and hop/auth. While the libraries have no tag for
| the v9 / silent re-check release, composer.json points at their branches (VCS repositories); "composer update"
| must not move the lock to whatever the branch holds today. Swap this test with the tag pins at release time.
*/

it('pins hop/ui and hop/auth to the commits in the lock', function () {
    $json = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
    $locked = fn (string $name) => collect($lock['packages'])->firstWhere('name', $name);

    expect($json['require']['hop/ui'])->toBe('dev-feat/manifest-v9 as 0.2.99')
        ->and($locked('hop/ui')['source']['reference'])->toBe('5f264d122b2a6ec12f4883af4db0d278ea6f9304')
        ->and($json['require']['hop/auth'])->toBe('dev-feat/pay-host-silent-recheck as 0.1.99')
        ->and($locked('hop/auth')['source']['reference'])->toBe('3388415b3355dc41c427833f0573ed8d05285725');
});
