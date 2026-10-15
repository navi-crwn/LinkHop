<?php

use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\User;

function iconsPage(array $attributes = []): BioPage
{
    return BioPage::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => 'icons',
        'title' => 'Icons',
        'theme' => 'lavender',
        'is_published' => true,
    ], $attributes));
}

test('a brand block on a light theme button gets a dark icon', function () {
    $page = iconsPage();
    // No btn_icon_invert: the column default decides, as for blocks made before the editor set it.
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'Instagram', 'url' => 'https://instagram.com/x', 'brand' => 'instagram', 'is_active' => true, 'order' => 1]);

    $html = $this->get('/b/icons')->assertOk()->getContent();

    expect($html)->toMatch('#/images/brands/instagram\.svg[^>]*style="filter: brightness\(0\);"#s');
});

test('social icons without an enabled flag show on the public page like in the preview', function () {
    iconsPage(['social_links' => [
        ['platform' => 'github', 'url' => 'https://github.com/navi-crwn'],
        ['platform' => 'reddit', 'value' => 'helink'],
        ['platform' => 'x', 'value' => 'helink', 'enabled' => false],
        ['platform' => 'website', 'value' => '', 'enabled' => true],
    ]]);

    $html = $this->get('/b/icons')->assertOk()->getContent();

    expect($html)
        ->toContain('/images/brands/github.svg')
        ->toContain('/images/brands/reddit.svg')
        ->not->toContain('/images/brands/x.svg')
        ->not->toContain('/images/brands/website.svg');
});

test('the public avatar has the same frame as the preview', function () {
    iconsPage();

    expect($this->get('/b/icons')->assertOk()->getContent())->toContain('class="bio-avatar ');
});
