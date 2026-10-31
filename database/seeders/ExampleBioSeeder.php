<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\User;
use Hop\Bio\Models\BioPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Optional development examples: a bio page (/b/hel) and project-resource short links.
 * Run with `php artisan db:seed --class=ExampleBioSeeder`; running it again resets the example.
 * A slug already taken by another account is left alone, so this never touches real users' pages.
 * To give the example to an existing account (e.g. the site owner who already has /b/hel), set
 * EXAMPLE_BIO_OWNER=that@email; its current /b/hel page and these short links are then replaced.
 */
class ExampleBioSeeder extends Seeder
{
    public const REPO = 'https://github.com/navi-crwn/LinkHop';

    public const SITE = 'https://navi-crwn.github.io/LinkHop/';

    /** Short slug => where it goes. */
    public const SHORT_LINKS = [
        'github' => self::REPO,
        'website' => self::SITE,
        'selfhost' => self::REPO.'/blob/main/README.md#install',
        // Not 'api-docs': hel.ink/api-docs is the app's own API page for members, which wins over short links.
        'documentation' => self::REPO.'/blob/main/README.md#api',
        'changelog' => self::REPO.'/blob/main/CHANGELOG.md',
    ];

    /** Short links earlier versions made; removed when they still belong to the owner and point where they did. */
    public const OLD_SHORT_LINKS = [
        'api-docs' => 'https://navi-crwn.github.io/hel.ink/#api',
    ];

    public function run(): void
    {
        $owner = env('EXAMPLE_BIO_OWNER');
        $user = $owner ? User::where('email', $owner)->firstOrFail() : User::firstOrCreate(['email' => 'example@hel.ink'], [
            'name' => 'LinkHop',
            'password' => bcrypt(str()->random(32)),
            'email_verified_at' => now(),
        ]);

        foreach (self::OLD_SHORT_LINKS as $slug => $target) {
            Link::where('slug', $slug)->where('user_id', $user->id)->where('target_url', $target)->get()->each->delete();
        }

        foreach (self::SHORT_LINKS as $slug => $target) {
            if ($this->takenByOthers(Link::where('slug', $slug)->first(), $user, "short link /{$slug}")) {
                continue;
            }
            Link::updateOrCreate(['slug' => $slug], ['user_id' => $user->id, 'target_url' => $target, 'status' => 'active']);
        }

        if (! bio_enabled()) {
            $this->command?->warn('Bio is off (BIO_ENABLED): the example short links were seeded, the example bio page was not.');

            return;
        }
        $existing = BioPage::where('slug', 'hel')->first();
        if ($this->takenByOthers($existing, $user, 'bio page /b/hel')) {
            return;
        }

        Storage::disk('public')->put('bio/avatars/hel-example.png', File::get(base_path('docs/logo.png')));

        $existing?->delete();
        $page = BioPage::create([
            'user_id' => $user->id,
            'slug' => 'hel',
            'title' => 'LinkHop',
            'bio' => 'Short links and link in bio pages. Free, open source, and yours to host.',
            'avatar_url' => 'bio/avatars/hel-example.png',
            'avatar_shape' => 'rounded',
            'theme' => 'galaxy',
            'font_family' => 'inter',
            'block_shape' => 'rounded',
            'block_shadow' => 'md',
            'background_animation' => 'stars',
            'social_links' => [
                ['platform' => 'github', 'value' => self::REPO, 'enabled' => true],
                ['platform' => 'website', 'value' => self::SITE, 'enabled' => true],
                ['platform' => 'email', 'value' => 'hello@hel.ink', 'enabled' => true],
            ],
            'is_published' => true,
            'is_public' => true,
            'allow_indexing' => true,
        ]);

        $blocks = [
            ['type' => 'header', 'title' => 'Start here'],
            ['type' => 'link', 'title' => 'Shorten a link', 'url' => 'https://hel.ink', 'brand' => 'link', 'attention_animation' => 'glow'],
            ['type' => 'link', 'title' => 'Make your own bio page', 'url' => 'https://hel.ink/register', 'brand' => 'website'],
            ['type' => 'link', 'title' => 'Source code on GitHub', 'url' => self::REPO, 'brand' => 'github'],
            ['type' => 'link', 'title' => 'Docs and self-host guide', 'url' => self::SITE, 'brand' => 'blog'],
            ['type' => 'divider'],
            ['type' => 'text', 'content' => '<p>Every short link counts its clicks by country, device and referrer.</p>'],
            ['type' => 'faq', 'title' => 'FAQ', 'faq_items' => [
                ['question' => 'Do I need an account?', 'answer' => 'Not to shorten a link. With an account you keep your links and get analytics, folders, the API and a bio page.'],
                ['question' => 'Can I host it myself?', 'answer' => 'Yes. LinkHop is open source under the MIT license; the self-host guide is on the docs site.'],
            ]],
            ['type' => 'vcard', 'title' => 'Save contact', 'vcard_name' => 'LinkHop', 'vcard_email' => 'hello@hel.ink', 'vcard_company' => 'Open source'],
        ];
        foreach ($blocks as $i => $block) {
            $page->links()->create($block + ['order' => $i + 1, 'is_active' => true]);
        }
    }

    private function takenByOthers(?object $model, User $user, string $what): bool
    {
        if ($model && $model->user_id !== $user->id) {
            $this->command?->warn("Skipped the {$what}: another account already uses it.");

            return true;
        }

        return false;
    }
}
