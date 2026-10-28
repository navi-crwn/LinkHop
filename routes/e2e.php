<?php

/*
| Test-only hooks for the synthetic-user E2E suite (tests/e2e). Loaded only when
| E2E_HOOKS=true and never in production (see routes/web.php).
*/

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::prefix('_e2e')->withoutMiddleware(ValidateCsrfToken::class)->group(function () {
    // Sign a persona in without the hub: what the SSO callback does after a verified ticket.
    Route::post('/login', function (Request $request) {
        $user = User::where('email', (string) $request->input('email'))->firstOrFail();
        Auth::loginUsingId($user->id, remember: true);
        $request->session()->regenerate();

        return ['ok' => true, 'id' => $user->id];
    });
    // A fresh, verified account with its own data; every marker contains a unique
    // token so pages can be checked for data leaking between users.
    Route::post('/persona', function (Request $request) {
        $token = 'zq'.Str::lower(Str::random(8));
        $admin = $request->boolean('admin');
        $email = $admin ? config('auth.superadmin_email') : "{$token}@e2e.test";
        $user = User::firstOrCreate(['email' => $email], [
            'name' => ($admin ? 'Admin ' : 'Persona ').$token,
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['email_verified_at' => now(), 'status' => 'active'])->save();
        $folder = Folder::create(['user_id' => $user->id, 'name' => "F{$token}"]);
        Tag::create(['user_id' => $user->id, 'name' => "T{$token}"]);
        foreach (['a', 'b'] as $suffix) {
            Link::create(['user_id' => $user->id, 'folder_id' => $folder->id, 'slug' => "{$token}{$suffix}",
                'target_url' => "https://example.com/{$token}/{$suffix}", 'status' => Link::STATUS_ACTIVE]);
        }
        $page = BioPage::create(['user_id' => $user->id, 'slug' => "{$token}b", 'title' => "Bio {$token}", 'theme' => 'default', 'is_published' => true]);
        foreach (range(1, 3) as $i) {
            BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => "{$token}-{$i}",
                'url' => "https://example.com/{$token}/{$i}", 'is_active' => true, 'order' => $i - 1]);
        }
        $locked = BioPage::create(['user_id' => $user->id, 'slug' => "{$token}l", 'title' => "Locked {$token}", 'theme' => 'default',
            'is_published' => true, 'password_enabled' => true, 'password' => Hash::make('secret123')]);
        BioLink::create(['bio_page_id' => $locked->id, 'type' => 'link', 'title' => 'Hidden',
            'url' => "https://example.com/secret-{$token}", 'is_active' => true, 'order' => 0]);

        return ['token' => $token, 'email' => $email, 'password' => 'password', 'admin' => $admin,
            'bio' => $page->slug, 'locked' => $locked->slug, 'lockedSecret' => "secret-{$token}", 'links' => ["{$token}a", "{$token}b"]];
    });

    // Data invariants checked after journeys (empty list = healthy).
    Route::get('/invariants', function () {
        $violations = [];
        $count = fn (string $sql) => (int) DB::selectOne($sql)->n;
        if ($n = $count('select count(*) n from bio_links where bio_page_id not in (select id from bio_pages)')) {
            $violations[] = "{$n} bio blocks without a page";
        }
        if ($n = $count('select count(*) n from link_clicks where link_id not in (select id from links)')) {
            $violations[] = "{$n} link clicks without a link";
        }
        if ($n = DB::table('bio_links')->select('bio_page_id', 'order')->groupBy('bio_page_id', 'order')->havingRaw('count(*) > 1')->get()->count()) {
            $violations[] = "{$n} bio pages with duplicate block positions";
        }
        if ($n = $count("select count(*) n from bio_pages where is_published = 1 and user_id in (select id from users where status = 'suspended')")) {
            $violations[] = "{$n} published bio pages owned by suspended users";
        }

        return ['violations' => $violations, 'failed_jobs' => DB::table('failed_jobs')->count()];
    });
});
