<?php

use App\Models\AbuseFlag;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\DomainBlacklist;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\File;

function bioPageFor(User $user, string $slug, array $attrs = []): BioPage
{
    return BioPage::create(array_merge([
        'user_id' => $user->id,
        'slug' => $slug,
        'title' => 'Page '.$slug,
        'theme' => 'default',
        'is_published' => true,
    ], $attrs));
}

function blockOn(BioPage $page, array $attrs = []): BioLink
{
    return BioLink::create(array_merge([
        'bio_page_id' => $page->id,
        'type' => 'link',
        'title' => 'My link',
        'url' => 'https://example.com',
        'is_active' => true,
        'order' => 0,
    ], $attrs));
}

test('a user cannot edit or delete a block that belongs to another page', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $alicePage = bioPageFor($alice, 'alicepage');
    $bobBlock = blockOn(bioPageFor($bob, 'bobpage'));

    $this->actingAs($alice)
        ->putJson("/bio/{$alicePage->slug}/links/{$bobBlock->id}", ['title' => 'pwned'])
        ->assertNotFound();
    $this->actingAs($alice)
        ->deleteJson("/bio/{$alicePage->slug}/links/{$bobBlock->id}")
        ->assertNotFound();
    $this->actingAs($alice)
        ->postJson("/bio/{$alicePage->slug}/links/{$bobBlock->id}/toggle")
        ->assertNotFound();

    expect($bobBlock->fresh()->title)->toBe('My link');
});

test('bio blocks reject dangerous url schemes and blacklisted domains', function () {
    $user = User::factory()->create();
    $page = bioPageFor($user, 'mypage');
    DomainBlacklist::create(['domain' => 'evil.test', 'match_type' => 'exact', 'category' => 'malware']);

    $this->actingAs($user)
        ->postJson("/bio/{$page->slug}/links", ['type' => 'link', 'title' => 'x', 'url' => 'javascript:alert(1)'])
        ->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->actingAs($user)
        ->postJson("/bio/{$page->slug}/links", ['type' => 'link', 'title' => 'x', 'url' => 'https://www.evil.test/a'])
        ->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->actingAs($user)
        ->postJson("/bio/{$page->slug}/links", ['type' => 'link', 'title' => 'Mail me', 'url' => 'mailto:me@example.com'])
        ->assertOk();
});

test('bio shorten endpoint enforces the blacklist', function () {
    $user = User::factory()->create();
    DomainBlacklist::create(['domain' => '*.evil.test', 'match_type' => 'wildcard', 'category' => 'phishing']);

    $this->actingAs($user)
        ->postJson('/api/bio/shorten', ['url' => 'https://login.evil.test/x'])
        ->assertUnprocessable();
    $this->actingAs($user)
        ->postJson('/api/bio/shorten', ['url' => 'https://example.org/page'])
        ->assertCreated();
    expect(Link::count())->toBe(1);
});

test('public click only redirects active blocks on published pages and is tracked', function () {
    $user = User::factory()->create();
    $page = bioPageFor($user, 'clicks');
    $active = blockOn($page, ['url' => 'https://example.com/a']);
    $inactive = blockOn($page, ['url' => 'https://example.com/b', 'is_active' => false]);

    $this->get("/b/click/{$active->id}")->assertRedirect('https://example.com/a');
    expect($active->fresh()->click_count)->toBe(1);
    $this->get("/b/click/{$inactive->id}")->assertNotFound();

    $page->update(['is_published' => false]);
    $this->get("/b/click/{$active->id}")->assertNotFound();
});

test('public bio page sanitizes user html and custom css', function () {
    $user = User::factory()->create();
    $page = bioPageFor($user, 'xsspage', ['custom_css' => 'a{color:red}</style><script>alert(1)</script>']);
    blockOn($page, ['type' => 'html', 'url' => '', 'html_content' => '<p>hi<img src=x onerror=alert(1)><a href="javascript:alert(2)">j</a></p>']);
    blockOn($page, ['type' => 'link', 'title' => 'Bad', 'url' => 'javascript:alert(3)', 'order' => 2]);

    $html = $this->get('/b/xsspage')->assertOk()->getContent();

    expect($html)->not->toContain('onerror')
        ->not->toContain('javascript:alert')
        ->not->toContain('</style><script>alert(1)');
});

test('abuse scan dry run changes nothing', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'slotgacor', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->artisan('abuse:scan', ['--full' => true, '--dry-run' => true])->assertSuccessful();

    expect($link->fresh()->status)->toBe('active');
    expect(AbuseFlag::count())->toBe(0);
});

test('abuse scan disables abusive links and suspends the owner', function () {
    $spammer = User::factory()->create();
    $normal = User::factory()->create();
    DomainBlacklist::create(['domain' => 'evil.test', 'match_type' => 'exact', 'category' => 'malware']);
    $bad = Link::create(['user_id' => $spammer->id, 'slug' => 'abc123', 'target_url' => 'https://evil.test/x', 'status' => 'active']);
    $other = Link::create(['user_id' => $spammer->id, 'slug' => 'def456', 'target_url' => 'https://example.com', 'status' => 'active']);
    $fine = Link::create(['user_id' => $normal->id, 'slug' => 'ghi789', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->artisan('abuse:scan', ['--full' => true])->assertSuccessful();

    expect($bad->fresh()->status)->toBe('inactive')
        ->and($other->fresh()->status)->toBe('inactive')
        ->and($spammer->fresh()->status)->toBe(User::STATUS_SUSPENDED)
        ->and($fine->fresh()->status)->toBe('active')
        ->and($normal->fresh()->status)->toBe(User::STATUS_ACTIVE);
    expect(AbuseFlag::where('subject_type', 'link')->where('subject_id', $bad->id)->value('action'))->toBe('disabled');
});

test('abuse scan respects dismissed findings and purges old disabled items', function () {
    $user = User::factory()->create();
    $restored = Link::create(['user_id' => $user->id, 'slug' => 'gacor01', 'target_url' => 'https://example.com', 'status' => 'active']);
    AbuseFlag::create(['subject_type' => 'link', 'subject_id' => $restored->id, 'score' => 60, 'signals' => ['gambling'], 'action' => 'disabled', 'status' => 'dismissed']);
    $old = Link::create(['user_id' => $user->id, 'slug' => 'oldspam', 'target_url' => 'https://example.com', 'status' => 'inactive']);
    $flag = AbuseFlag::create(['subject_type' => 'link', 'subject_id' => $old->id, 'score' => 60, 'signals' => ['gambling'], 'action' => 'disabled', 'status' => 'open']);
    $flag->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->artisan('abuse:scan', ['--full' => true, '--purge' => true])->assertSuccessful();

    expect($restored->fresh()->status)->toBe('active')
        ->and(Link::find($old->id))->toBeNull()
        ->and($flag->fresh()->action)->toBe('purged');
});

test('admin can review and restore automated findings', function () {
    config(['auth.superadmin_email' => 'root@example.com']);
    $admin = User::factory()->create(['email' => 'root@example.com']);
    $owner = User::factory()->create();
    $link = Link::create(['user_id' => $owner->id, 'slug' => 'falsepos', 'target_url' => 'https://example.com', 'status' => 'inactive']);
    $flag = AbuseFlag::create(['subject_type' => 'link', 'subject_id' => $link->id, 'user_id' => $owner->id, 'score' => 60, 'signals' => ['gambling'], 'action' => 'disabled', 'summary' => '/falsepos -> example.com']);

    $this->actingAs($admin)->get('/admin/abuse')->assertOk()->assertSee('/falsepos -&gt; example.com', false);
    $this->actingAs($owner)->post("/admin/abuse/flags/{$flag->id}/restore")->assertForbidden();
    $this->actingAs($admin)->post("/admin/abuse/flags/{$flag->id}/restore")->assertRedirect();

    expect($link->fresh()->status)->toBe('active')
        ->and($flag->fresh()->status)->toBe('dismissed');
});

test('link password attempts are rate limited', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'locked1', 'target_url' => 'https://example.com', 'status' => 'active', 'password_hash' => bcrypt('right-pass')]);

    foreach (range(1, 10) as $i) {
        $this->post('/locked1', ['password' => 'wrong'.$i])->assertSessionHasErrors('password');
    }
    $this->post('/locked1', ['password' => 'right-pass'])->assertSessionHasErrors('password');
});

test('an expired link can still be edited', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'oldone', 'target_url' => 'https://example.com', 'status' => 'active']);
    $link->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($user)->put("/links/{$link->id}", [
        'target_url' => 'https://example.com/new',
        'slug' => 'oldone',
        'status' => 'active',
        'expires_at' => now()->subDay()->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors();
    expect($link->fresh()->target_url)->toBe('https://example.com/new');
});

test('urls listed in the local threat feeds are rejected and scanned', function () {
    $dir = storage_path('app/threat-feeds');
    File::ensureDirectoryExists($dir);
    file_put_contents("{$dir}/phishing.txt", "phish.example/login\nother.example/x");
    file_put_contents("{$dir}/malware.txt", '');
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/bio/shorten', ['url' => 'https://www.phish.example/login/'])->assertUnprocessable();
    // Same host, different URL: shared platforms are not blocked wholesale.
    $this->actingAs($user)->postJson('/api/bio/shorten', ['url' => 'https://phish.example/about'])->assertCreated();

    unlink("{$dir}/phishing.txt");
    unlink("{$dir}/malware.txt");
});

test('vcard values cannot break out of the inline handler', function () {
    $user = User::factory()->create();
    $page = bioPageFor($user, 'vcardpage');
    blockOn($page, ['type' => 'vcard', 'url' => '', 'vcard_name' => "x');alert(document.domain);//"]);

    $html = $this->get('/b/vcardpage')->assertOk()->getContent();

    // The quote stays inside the JS string (\u0027) instead of closing it.
    expect($html)->not->toContain("downloadVCard('x');alert")
        ->not->toContain('downloadVCard(&#039;x&#039;);alert')
        ->toContain('downloadVCard(\'x\u0027);alert');
});

test('uploaded images resolve on the public disk whatever the default disk is', function () {
    config(['filesystems.default' => 's3']);
    bioPageFor(User::factory()->create(), 'avatarpage', ['avatar_url' => 'avatars/me.jpg']);

    $this->get('/b/avatarpage')->assertOk()->assertSee('/storage/avatars/me.jpg', false);
});

test('an admin can edit any user link, other users cannot', function () {
    config(['auth.superadmin_email' => 'boss@example.com']);
    $owner = User::factory()->create();
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    $link = Link::create(['user_id' => $owner->id, 'slug' => 'sneaky', 'target_url' => 'https://example.com', 'status' => 'active']);
    $payload = ['target_url' => 'https://example.com/safe', 'slug' => 'sneaky', 'status' => 'inactive'];

    $this->actingAs(User::factory()->create())->put("/links/{$link->id}", $payload)->assertForbidden();
    $this->actingAs($admin)->get("/links/{$link->id}/edit")->assertOk();
    $this->actingAs($admin)->put("/links/{$link->id}", $payload)->assertSessionHasNoErrors();
    expect($link->fresh())->target_url->toBe('https://example.com/safe')->status->toBe('inactive')->user_id->toBe($owner->id);
});

test('remember me lasts 30 days', function () {
    $user = User::factory()->create();
    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);

    $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeBetween(now()->addDays(30)->subMinute()->timestamp, now()->addDays(30)->addMinute()->timestamp);
});
