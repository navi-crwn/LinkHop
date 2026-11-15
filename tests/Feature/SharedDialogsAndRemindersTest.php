<?php

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\UserDeletion;
use App\Support\Reminders;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
| The family's dialogs (hop-assets hop-ui, window.HopUI) take the place of confirm(), alert() and prompt(), and
| reminder cards can be hidden for a week. hop-ui is loaded only when the hub's manifest lists it, so the dashboard
| has to work with and without it.
*/

function remindUser(): User
{
    return User::factory()->create();
}

/** A link that ticks every step of the checklist except the folder-or-tag one. */
function remindLink(User $user, array $attributes = []): Link
{
    return Link::create([
        'user_id' => $user->id,
        'slug' => 'rm'.fake()->unique()->numberBetween(1000, 99999),
        'target_url' => 'https://example.com/page',
        'status' => Link::STATUS_ACTIVE,
        ...$attributes,
    ]);
}

/** Days later, still signed in: without this the inactivity logout answers with a redirect. */
function remindLater(TestCase $test, int $days): void
{
    $test->travel($days)->days();
    $test->withSession(['last_activity_time' => now()]);
}

function manifestWithUi(): array
{
    return [
        'version' => 'v5',
        'base' => 'https://hel.ink/hop-assets/v5/',
        'assets' => [
            'fonts_css' => ['url' => 'https://hel.ink/hop-assets/v5/fonts-amnesia.css', 'integrity' => 'sha384-fonts'],
            'ui_css' => ['url' => 'https://hel.ink/hop-assets/v5/hop-ui.css', 'integrity' => 'sha384-uicss'],
            'ui_js' => ['url' => 'https://hel.ink/hop-assets/v5/hop-ui.js', 'integrity' => 'sha384-uijs'],
        ],
        'apps' => [],
    ];
}

it('stores when a reminder card was hidden, per account and card', function () {
    $user = remindUser();
    $other = remindUser();

    $this->actingAs($user)->postJson(route('reminders.dismiss'), ['key' => 'checklist'])
        ->assertOk()->assertJson(['ok' => true, 'key' => 'checklist'])->assertJsonStructure(['shows_again_at']);

    $row = DB::table('reminder_dismissals')->where('user_id', $user->id)->where('key', 'checklist')->first();
    expect($row)->not->toBeNull()->and($row->dismissed_at)->not->toBeNull()
        ->and(Reminders::hidden($user, 'checklist'))->toBeTrue()
        ->and(Reminders::hidden($user, 'short-links-move'))->toBeFalse()
        ->and(Reminders::hidden($other, 'checklist'))->toBeFalse();

    // Hiding again moves the date; it does not add a row.
    remindLater($this, 2);
    $this->postJson(route('reminders.dismiss'), ['key' => 'checklist'])->assertOk();
    expect(DB::table('reminder_dismissals')->where('user_id', $user->id)->count())->toBe(1);
});

it('refuses a card it does not know, and a visitor who is not signed in', function () {
    $user = remindUser();

    $this->postJson(route('reminders.dismiss'), ['key' => 'checklist'])->assertUnauthorized();
    $this->actingAs($user)->postJson(route('reminders.dismiss'), ['key' => 'something-else'])->assertStatus(422);
    $this->postJson(route('reminders.dismiss'), [])->assertStatus(422);
    expect(DB::table('reminder_dismissals')->count())->toBe(0);
});

it('keeps a hidden checklist away for seven days, then shows it again while steps are open', function () {
    $user = remindUser();
    remindLink($user);
    $this->actingAs($user);

    $this->get('/dashboard')->assertOk()->assertSee('data-hop-remind="checklist"', false)
        ->assertSee('data-hop-remind-url="'.route('reminders.dismiss').'"', false)->assertSee('Get the most out of LinkHop');

    $this->postJson(route('reminders.dismiss'), ['key' => 'checklist'])->assertOk();
    $this->get('/dashboard')->assertOk()->assertDontSee('data-hop-remind="checklist"', false)->assertDontSee('Get the most out of LinkHop');

    remindLater($this, 6);
    $this->get('/dashboard')->assertOk()->assertDontSee('Get the most out of LinkHop');

    remindLater($this, 2);
    $this->get('/dashboard')->assertOk()->assertSee('data-hop-remind="checklist"', false)->assertSee('Get the most out of LinkHop');
});

it('drops the checklist for good once every step is done', function () {
    $user = remindUser();
    remindLink($user, ['clicks' => 1, 'password_hash' => bcrypt('x'), 'custom_title' => 'Hello', 'qr_fg_color' => '#1e40af']);
    Tag::create(['user_id' => $user->id, 'name' => 'x']);

    $this->actingAs($user)->get('/dashboard')->assertOk()
        ->assertDontSee('data-hop-remind="checklist"', false)->assertDontSee('Get the most out of LinkHop')->assertSee('Clicks this week');
    expect(DB::table('reminder_dismissals')->count())->toBe(0);
});

it('removes the reminder rows with the account', function () {
    $user = remindUser();
    $keep = remindUser();
    Reminders::dismiss($user, 'checklist');
    Reminders::dismiss($keep, 'checklist');

    app(UserDeletion::class)->purge($user);

    expect(DB::table('reminder_dismissals')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('reminder_dismissals')->where('user_id', $keep->id)->count())->toBe(1);
});

it('creates the reminder table once and leaves it alone on a second run', function () {
    $migration = require database_path('migrations/2026_11_12_000001_create_reminder_dismissals_table.php');
    Reminders::dismiss(remindUser(), 'checklist');

    $migration->up();
    $migration->up();

    expect(DB::table('reminder_dismissals')->count())->toBe(1);
});

it('loads hop-ui with its SRI hashes and the nonce once the manifest lists it', function () {
    config(['hop-ui.assets.remote' => true]);
    Cache::forget('hop-ui:assets');
    Http::fake(['hel.ink/hop-assets/apps.json' => Http::response(manifestWithUi())]);

    $html = $this->actingAs(remindUser())->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('href="https://hel.ink/hop-assets/v5/hop-ui.css" integrity="sha384-uicss" crossorigin="anonymous"')
        ->toMatch('~<script nonce="[^"]+" src="https://hel\.ink/hop-assets/v5/hop-ui\.js" integrity="sha384-uijs" crossorigin="anonymous" defer></script>~');
    Cache::forget('hop-ui:assets');
});

it('leaves hop-ui out while the manifest does not list it, and still renders the page', function () {
    // The bundled manifest lists hop-ui since v9, so the page is rendered against one that does not.
    $manifest = manifestWithUi();
    unset($manifest['assets']['ui_css'], $manifest['assets']['ui_js']);
    config(['hop-ui.assets.remote' => true]);
    Cache::forget('hop-ui:assets');
    Http::fake(['hel.ink/hop-assets/apps.json' => Http::response($manifest)]);

    $html = $this->actingAs(remindUser())->get('/dashboard')->assertOk()->getContent();

    expect($html)->not->toContain('hop-ui.js')->not->toContain('hop-ui.css');
    Cache::forget('hop-ui:assets');
});

it('allows hel.ink for scripts and styles in the content security policy', function () {
    $csp = (string) $this->actingAs(remindUser())->get('/dashboard')->headers->get('Content-Security-Policy');

    expect($csp)->toMatch('~script-src [^;]*https://hel\.ink~')->toMatch('~style-src [^;]*https://hel\.ink~');
});

it('asks before a delete with a red, named button: folders, tags, links in a folder, webhooks', function () {
    $user = remindUser();
    $folder = Folder::create(['name' => 'Campaign <b>', 'user_id' => $user->id]);
    $tag = Tag::create(['name' => 'social', 'user_id' => $user->id]);
    $link = remindLink($user, ['slug' => 'askme1', 'folder_id' => $folder->id]);
    $this->actingAs($user);

    $folders = $this->get(route('folders.index'))->assertOk()->getContent();
    expect($folders)->toContain('data-confirm="Delete this folder?"')->toContain('data-confirm-danger')
        ->toContain('data-confirm-subject="Campaign &lt;b&gt;"');

    $manage = $this->get(route('folders.manage', $folder))->assertOk()->getContent();
    expect($manage)->toContain('data-confirm="Delete this folder?"')
        ->toContain('data-confirm="Delete this link permanently?"')->toContain('data-confirm-subject="/l/askme1"');

    $tags = $this->get(route('tags.manage', $tag))->assertOk()->getContent();
    expect($tags)->toContain('data-confirm="Delete this tag?"')->toContain('data-confirm-subject="social"');

    $links = $this->get(route('links.index'))->assertOk()->getContent();
    expect($links)->toContain('window.hopDialog.confirm(')->toContain("deleteLink('".$link->id."'")->toContain('bulkDelete($el)');

    // A webhook is named by its host only: the path may hold a token.
    WebhookEndpoint::create(['user_id' => $user->id, 'url' => 'https://hooks.example.net/in?token=abc', 'secret' => 'x', 'events' => ['link.created'], 'is_active' => true]);
    $settings = $this->get(route('settings'))->assertOk()->getContent();
    expect($settings)->toContain('data-confirm="Delete this webhook and its log?"')->toContain('data-confirm-subject="hooks.example.net"')
        ->toContain('data-confirm="Make a new secret?"')->not->toContain('token=abc');
});

it('has no confirm(), alert() or prompt() of the browser left in the dashboard, outside the fallback', function () {
    $files = collect(File::allFiles(resource_path('views')))->merge(File::allFiles(resource_path('js')))
        ->reject(fn ($file) => str_ends_with($file->getPathname(), 'dialogs.js') || str_contains($file->getPathname(), 'vendor'.DIRECTORY_SEPARATOR.'mail'));
    $found = [];
    foreach ($files as $file) {
        foreach (explode("\n", $file->getContents()) as $n => $line) {
            if (preg_match('/(?<![\w.$])(?:window\.)?(?:confirm|alert|prompt)\(/', $line) && ! str_contains($line, 'hopDialog.')) {
                $found[] = $file->getRelativePathname().':'.($n + 1);
            }
        }
    }

    expect($found)->toBe([]);
    $fallback = file_get_contents(resource_path('js/dialogs.js'));
    expect($fallback)->toContain('window.HopUI')->toContain('window.confirm(')->toContain('window.alert(')->toContain('window.prompt(');
});

it('titles and explains the questions in every language', function () {
    foreach (['Delete this folder?', 'Delete this tag?', 'Delete this link permanently?', 'Make a new secret?', 'Delete this webhook and its log?',
        'The short link stops working and its statistics are removed. This cannot be undone.', 'Links keep working but lose this tag.'] as $key) {
        foreach (['zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $lang) {
            expect(trans($key, [], $lang))->not->toBe($key, "{$key} is not translated into {$lang}");
        }
    }
});
