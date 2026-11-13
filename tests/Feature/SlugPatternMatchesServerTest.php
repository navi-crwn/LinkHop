<?php

use App\Http\Requests\ManageLinkRequest;
use App\Http\Requests\ShortenLinkRequest;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/*
| Every slug field lets the browser pass exactly what the server accepts (letters, digits and hyphens, 3 to 48).
| The create dialog used to allow "_", so the browser sent my_link and the server answered 422.
*/

/** @return list<string> the pattern="" of every input named or modelled as the slug in the HTML */
function slugPatterns(string $html): array
{
    preg_match_all('~<input\b[^>]*(?:name="slug"|x-model="formData\.slug")[^>]*>~', $html, $inputs);

    return array_map(fn ($tag) => preg_match('~pattern="([^"]*)"~', $tag, $m) ? html_entity_decode($m[1]) : '', $inputs[0]);
}

it('gives every slug field the server rule as its pattern', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'mine-01', 'target_url' => 'https://example.com', 'status' => 'active']);
    $pages = [
        'guest form' => $this->get('/')->getContent(),
        'create dialog' => $this->actingAs($user)->get('/dashboard')->getContent(),
        'create page' => $this->actingAs($user)->get('/links/create')->getContent(),
        'edit page' => $this->actingAs($user)->get('/links/'.$link->id.'/edit')->getContent(),
    ];
    $samples = ['abc', 'my-link', 'MyLink-2', 'my_link', 'ab', str_repeat('x', 48), str_repeat('x', 49), 'my link', 'my.link', 'ünï'];
    $server = fn (string $slug) => Validator::make(['slug' => $slug], ['slug' => ['regex:/^[a-z0-9-]+$/i', 'min:3', 'max:48']])->passes();

    expect((new ManageLinkRequest)->rules()['slug'])->toContain('regex:/^[a-z0-9-]+$/i', 'min:3', 'max:48')
        ->and((new ShortenLinkRequest)->rules()['slug'])->toContain('regex:/^[a-z0-9-]+$/i', 'min:3', 'max:48');
    foreach ($pages as $name => $html) {
        $patterns = slugPatterns($html);
        expect($patterns)->not->toBeEmpty($name);
        foreach ($patterns as $pattern) {
            foreach ($samples as $slug) {
                // the browser anchors a pattern at both ends and reads it with the v flag
                $browser = (bool) preg_match('/^(?:'.$pattern.')$/u', $slug);
                expect($browser)->toBe($server($slug), "{$name}: {$slug}");
            }
        }
    }
});
