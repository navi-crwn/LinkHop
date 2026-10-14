<?php

test('e2e hooks are not registered unless E2E_HOOKS is enabled', function () {
    $this->post('/_e2e/persona')->assertDontSee('"password":"password"', false);
    expect(collect(app('router')->getRoutes())->contains(fn ($r) => str_starts_with($r->uri(), '_e2e')))->toBeFalse();
});
