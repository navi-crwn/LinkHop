<?php

test('e2e hooks are not registered unless E2E_HOOKS is enabled', function () {
    $this->postJson('/_e2e/persona')->assertJsonMissing(['password' => 'password']);
    expect(collect(app('router')->getRoutes())->contains(fn ($r) => str_starts_with($r->uri(), '_e2e')))->toBeFalse();
});
