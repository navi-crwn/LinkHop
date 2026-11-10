<?php

test('the home page family section uses the hop-assets v7 engine and has no section numbering', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('~<h2 id="family-title".*?</h2>~s', $html, $head);
    preg_match('~<section[^>]*aria-labelledby="family-title".*?</section>~s', $html, $section);

    expect($section[0] ?? '')->toContain('data-hop-hero="filehop"')->toContain('data-hop-hero="notehop"')->not->toContain('h-label')
        ->not->toContain('· <span>HOP</span>')->toContain('hop-bento');
    // The vendored engine is the v7 file byte for byte; a private edit must go to hop-assets first.
    foreach (['hop-hero3d.js' => '4f64a82b7e901ca06e2c28c733cca4d2022db45fca429bf19836041df21eead2', 'hop-motion.js' => 'fabd66945b7f3abfdd2b84cb20a772ed52d1ba2df9600ad444fcaf7dcf0a1167', 'hop-bento.css' => '98a1973b3cd78c101e35f26d4bd99bdc33c8f35b7b5d3000c97f30df2cb38994'] as $file => $hash) {
        expect(hash_file('sha256', public_path('hop-home/'.$file)))->toBe($hash);
    }
});
