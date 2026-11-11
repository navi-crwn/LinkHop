<?php

test('the home page family section uses the hop-assets v9 engine and has no section numbering', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('~<h2 id="family-title".*?</h2>~s', $html, $head);
    preg_match('~<section[^>]*aria-labelledby="family-title".*?</section>~s', $html, $section);

    expect($section[0] ?? '')->toContain('data-hop-hero="filehop"')->toContain('data-hop-hero="notehop"')->toContain('data-hop-hero="payhop"')->not->toContain('h-label')
        ->not->toContain('· <span>HOP</span>')->toContain('hop-bento');
    // The vendored engine is the v9 file byte for byte; a private edit must go to hop-assets first.
    foreach (['hop-hero3d.js' => '9f711b669a8e201566f250ef2d9f7040e14549950afc8b7155e7902286b0a52d', 'hop-motion.js' => 'fabd66945b7f3abfdd2b84cb20a772ed52d1ba2df9600ad444fcaf7dcf0a1167', 'hop-bento.css' => '4a0345b7a856fe70e085c25503f08437cd21be0db303bccd39e29f666c5ef5ac'] as $file => $hash) {
        expect(hash_file('sha256', public_path('hop-home/'.$file)))->toBe($hash);
    }
});
