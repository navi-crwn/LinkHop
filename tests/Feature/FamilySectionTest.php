<?php

test('the home page family section uses the hop-assets v6 engine and has no section numbering', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('~<h2 id="family-title".*?</h2>~s', $html, $head);
    preg_match('~<section[^>]*aria-labelledby="family-title".*?</section>~s', $html, $section);

    expect($section[0] ?? '')->toContain('data-hop-hero="filehop"')->toContain('data-hop-hero="notehop"')->not->toContain('h-label')
        ->not->toContain('· <span>HOP</span>');
    // The vendored engine is the v6 file byte for byte; a private edit must go to hop-assets first.
    expect(hash_file('sha256', public_path('hop-home/hop-hero3d.js')))->toBe('b641de3aa2c45ebcbf36b2c3464983b5f1ab9bb4e617255c0709f266d3e17081');
});
