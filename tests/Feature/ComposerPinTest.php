<?php

/*
| composer.json and composer.lock name the same commit of hop/ui. "composer update hop/ui" would otherwise move the lock
| to whatever composer.json names.
*/

it('pins hop/ui to the commit in the lock', function () {
    $json = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
    $locked = collect($lock['packages'])->firstWhere('name', 'hop/ui');
    expect($json['require']['hop/ui'])->toBe('dev-main#'.$locked['source']['reference'].' as 0.2.99')
        ->and($locked['source']['reference'])->toBe('6b4210ce1cd42a7f3db9836112df4ea28c7c45cf');
});
