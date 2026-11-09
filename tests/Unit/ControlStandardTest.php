<?php

// Locks the HOP control standard (hop-ops docs/15): one radius, one height per row, buttons as wide as their text,
// 16px fields and 44px touch targets on phones. Measured in a browser by the audit; this guards the tokens it relies on.
$css = file_get_contents(__DIR__.'/../../resources/css/app.css');

/** The declaration block of the first rule whose selector line starts with $selector. */
function ruleBody(string $css, string $selector): string
{
    preg_match('/^\s*'.preg_quote($selector, '/').'\s*\{(.*?)\}/ms', $css, $m);

    return $m[1] ?? '';
}

it('defines the shared control tokens', function () use ($css) {
    foreach (['--hop-ctl-radius: 8px', '--hop-ctl-h: 36px', '--hop-ctl-h-sm: 32px', '--hop-ctl-h-lg: 40px'] as $token) {
        expect($css)->toContain($token);
    }
});

it('gives inputs the control height, radius and a width cap', function () use ($css) {
    $input = ruleBody($css, '.input');
    expect($input)->toContain('height: var(--hop-ctl-h)')->toContain('border-radius: var(--hop-ctl-radius)');
    expect($css)->toContain('max-width: var(--hop-field-max)');
});

it('gives every button variant one height and radius, sized to its text', function () use ($css) {
    $button = ruleBody($css, '.btn, .btn-primary, .btn-ghost, .btn-soft, .btn-outline, .btn-danger, .btn-danger-outline');
    expect($button)->toContain('height: var(--hop-ctl-h)')->toContain('border-radius: var(--hop-ctl-radius)')->toContain('width: auto')->toContain('white-space: nowrap');
});

it('uses 16px fields and 44px targets on a phone', function () use ($css) {
    expect($css)->toContain('.input, .input-dense { font-size: 16px; }')->toMatch('/pointer: coarse\) \{[^}]*min-height: 44px/s');
});
