<?php

test('the logo is the mark alone and the name is real text in the app typeface', function () {
    $html = $this->get('/features')->assertOk()->getContent();
    expect($html)->toContain('hop-brand/hop-brand.css')->toContain('hop-name hop-name-linkhop')->toContain('linkhop-mark.svg')
        ->not->toContain('linkhop-wordmark.svg');
    expect(file_get_contents(public_path('hop-brand/hop-brand.css')))->toContain('linkhop-name.woff2');
    foreach (['linkhop-name.woff2', 'biohop-name.woff2'] as $font) {
        expect(public_path('hop-brand/fonts/brand/'.$font))->toBeFile();
    }
});
