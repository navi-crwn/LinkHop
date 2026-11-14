<?php

use App\Http\Controllers\LinkController;
use App\Models\Link;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| The dashboard's "QR codes" page (/qr-codes): the codes of the member's short links and the Wi-Fi, contact and text
| codes the member saved. It holds permission code, so most of this is written from the side of someone who should
| not get in: a guest, another member, a made-up id. The saved content can be a Wi-Fi password: it is stored
| encrypted and never logged.
*/

const QC_PASS = 'hunter2-wifi-9'; // a made-up Wi-Fi password

function qcWifi(string $label = 'Cafe Wi-Fi', string $password = QC_PASS): array
{
    return ['type' => 'wifi', 'label' => $label, 'payload' => 'WIFI:T:WPA;S:Cafe Hop;P:'.$password.';;',
        'style' => ['fg' => '#000000', 'bg' => '#ffffff', 'dots' => 'square', 'corners' => 'square']];
}

function qcSave(User $user, array $body = []): QrCode
{
    $body = $body + qcWifi();

    return QrCode::create(['user_id' => $user->id, 'type' => $body['type'], 'label' => $body['label'], 'payload' => $body['payload'], 'style' => $body['style']]);
}

test('a guest is sent to sign in, on the page and on every action', function () {
    $owner = User::factory()->create();
    $qr = qcSave($owner);

    $this->get('/qr-codes')->assertRedirect();
    $this->postJson('/qr-codes', qcWifi())->assertStatus(401);
    $this->patch('/qr-codes/'.$qr->id, ['label' => 'Hijacked'])->assertRedirect();
    $this->delete('/qr-codes/'.$qr->id)->assertRedirect();

    $qr->refresh();
    expect($qr->label)->toBe('Cafe Wi-Fi')->and(QrCode::count())->toBe(1);
});

test('a member sees their own codes and their links, and the page is never stored by a cache', function () {
    $user = User::factory()->create();
    qcSave($user, ['label' => 'My office Wi-Fi']);
    Link::create(['user_id' => $user->id, 'slug' => 'withqr1', 'target_url' => 'https://example.com/a', 'status' => 'active', 'qr_code_path' => 'qrcodes/withqr1.png']);
    Link::create(['user_id' => $user->id, 'slug' => 'noqr001', 'target_url' => 'https://example.com/b', 'status' => 'active']);

    $res = $this->actingAs($user)->get('/qr-codes')->assertOk();
    $res->assertSee('My office Wi-Fi')->assertSee('/withqr1')->assertDontSee('/noqr001');
    expect($res->headers->get('Cache-Control'))->toContain('no-store');
    // inside the dashboard frame: the sidebar is there and the page is not the public tool
    $res->assertSee('qr-codes', false)->assertSee(route('dashboard'), false);
});

test('another member sees nothing of it', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    qcSave($owner, ['label' => 'Owners private Wi-Fi']);
    Link::create(['user_id' => $owner->id, 'slug' => 'ownerqr', 'target_url' => 'https://example.com/o', 'status' => 'active', 'qr_code_path' => 'qrcodes/ownerqr.png']);

    $html = $this->actingAs($other)->get('/qr-codes')->assertOk()->getContent();

    expect($html)->not->toContain('Owners private Wi-Fi')->not->toContain(QC_PASS)->not->toContain('/ownerqr');
});

test('the code of someone else is answered like a code that does not exist, for rename and delete', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $qr = qcSave($owner);
    $missing = (string) Str::ulid();

    foreach ([$qr->id, $missing] as $id) {
        $this->actingAs($attacker)->patch('/qr-codes/'.$id, ['label' => 'Hijacked'])->assertNotFound();
        $this->actingAs($attacker)->delete('/qr-codes/'.$id)->assertNotFound();
    }
    // an id that cannot be one is not even routed
    $this->actingAs($attacker)->delete('/qr-codes/1')->assertNotFound();
    $this->actingAs($attacker)->delete('/qr-codes/'.str_repeat('a', 26).'x')->assertNotFound();

    expect($qr->fresh()->label)->toBe('Cafe Wi-Fi')->and(QrCode::count())->toBe(1);
});

test('the owner saves, renames and deletes a code', function () {
    $user = User::factory()->create();

    $created = $this->actingAs($user)->postJson('/qr-codes', qcWifi('Home'))->assertCreated();
    $id = $created->json('id');
    expect($id)->toHaveLength(26)->and(QrCode::find($id)->user_id)->toBe($user->id);

    $this->actingAs($user)->patch('/qr-codes/'.$id, ['label' => 'Guest room'])->assertRedirect(route('qr-codes.index'));
    expect(QrCode::find($id)->label)->toBe('Guest room');

    $this->actingAs($user)->delete('/qr-codes/'.$id)->assertRedirect(route('qr-codes.index'));
    expect(QrCode::find($id))->toBeNull();
});

test('what a code carries is encrypted at rest and left out of logs and activity', function () {
    $user = User::factory()->create();
    $log = storage_path('logs/laravel.log');
    File::ensureDirectoryExists(dirname($log));
    $before = is_file($log) ? filesize($log) : 0;
    $activity = DB::table('activity_logs')->count();

    $this->actingAs($user)->postJson('/qr-codes', qcWifi())->assertCreated();

    $raw = (string) DB::table('qr_codes')->value('payload');
    expect($raw)->not->toContain(QC_PASS)->not->toContain('WIFI:')
        ->and(QrCode::first()->payload)->toContain(QC_PASS)
        ->and(DB::table('activity_logs')->count())->toBe($activity);
    $written = is_file($log) ? substr((string) file_get_contents($log), $before) : '';
    expect($written)->not->toContain(QC_PASS);
    // and the model never serialises it by accident
    expect(QrCode::first()->toArray())->not->toHaveKey('payload');
});

test('a made-up request cannot store what the page would not', function () {
    $user = User::factory()->create();
    $post = fn (array $over) => $this->actingAs($user)->postJson('/qr-codes', array_replace_recursive(qcWifi(), $over));

    $post(['type' => 'sms'])->assertStatus(422);
    $post(['payload' => 'just some text'])->assertStatus(422); // a Wi-Fi code that is not one
    $post(['payload' => 'WIFI:'.str_repeat('x', QrCode::MAX_PAYLOAD)])->assertStatus(422);
    $post(['label' => str_repeat('l', 81)])->assertStatus(422);
    $post(['style' => ['fg' => 'red']])->assertStatus(422);
    $post(['style' => ['dots' => 'zigzag']])->assertStatus(422);
    $this->actingAs($user)->postJson('/qr-codes', ['type' => 'wifi'])->assertStatus(422);
    // the address of an encrypted NoteHop note carries its key after the #: it is never kept here
    $this->actingAs($user)->postJson('/qr-codes', ['type' => 'text', 'label' => 'note', 'payload' => 'https://n.hel.ink/TOKEN123#k=SECRETKEY456',
        'style' => qcWifi()['style']])->assertStatus(422);
    expect(QrCode::count())->toBe(0);
});

test('a text code and a contact card are kept as such, with a default label', function () {
    $user = User::factory()->create();
    $style = qcWifi()['style'];

    $this->actingAs($user)->postJson('/qr-codes', ['type' => 'text', 'label' => '', 'payload' => 'https://example.com/menu', 'style' => $style])->assertCreated();
    $this->actingAs($user)->postJson('/qr-codes', ['type' => 'vcard', 'label' => 'Dewi', 'payload' => "BEGIN:VCARD\nVERSION:3.0\nFN:Dewi\nEND:VCARD", 'style' => $style])->assertCreated();

    expect(QrCode::where('type', 'text')->value('label'))->toBe('Link or text')
        ->and(QrCode::where('type', 'vcard')->value('label'))->toBe('Dewi');
});

test('an account keeps at most fifty saved codes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach (range(1, QrCode::MAX_PER_USER) as $i) {
        qcSave($user, ['label' => "Code $i"]);
    }

    $this->actingAs($user)->postJson('/qr-codes', qcWifi())->assertStatus(422)
        ->assertJsonPath('message', 'You can keep up to 50 saved QR codes. Delete one to save another.');
    // another account is not held back by it
    $this->actingAs($other)->postJson('/qr-codes', qcWifi())->assertCreated();
    expect(QrCode::where('user_id', $user->id)->count())->toBe(50);
});

test('saving and the other actions are rate limited', function () {
    $user = User::factory()->create();
    $last = null;
    foreach (range(1, 22) as $i) {
        $last = $this->actingAs($user)->deleteJson('/qr-codes/'.(string) Str::ulid());
    }

    $last->assertStatus(429);
});

test('the dashboard menu entry opens the member page, and the public bar keeps the public tool', function () {
    $this->get('/')->assertSee('href="'.route('qr').'"', false);
    $html = $this->actingAs(User::factory()->create())->get('/links')->assertOk()->getContent();

    expect($html)->toContain('href="'.route('qr-codes.index').'"')->not->toContain('href="'.route('qr').'"');
    // the public /qr page still opens for a guest and for a member
    $this->get('/qr')->assertOk();
    $this->actingAs(User::factory()->create())->get('/qr')->assertOk();
});

test('the dead route that saved a custom QR image into a column that does not exist is gone', function () {
    expect(Route::has('links.qr.save'))->toBeFalse()
        ->and(method_exists(LinkController::class, 'saveCustomQr'))->toBeFalse()
        ->and(Schema::hasColumn('links', 'qr_settings'))->toBeFalse();
});

/*
| The second reader's findings: the label is sealed like the content, one unreadable row cannot take the page or the
| export down, a refused form never puts what the code carries into the session, the cap holds under a lock, and
| nothing a member typed can end the data block of the page.
*/

test('the label is encrypted as well, so nothing taken from the content is readable in the database', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/qr-codes', qcWifi('Cafe Hop Lt 2'))->assertCreated();
    $this->actingAs($user)->postJson('/qr-codes', ['type' => 'vcard', 'label' => 'Dewi Lestari', 'payload' => "BEGIN:VCARD\nVERSION:3.0\nFN:Dewi Lestari\nEND:VCARD", 'style' => qcWifi()['style']])->assertCreated();
    $this->actingAs($user)->postJson('/qr-codes', ['type' => 'text', 'label' => 'https://example.com/private-menu', 'payload' => 'https://example.com/private-menu', 'style' => qcWifi()['style']])->assertCreated();

    $rows = DB::table('qr_codes')->get(['label', 'payload'])->map(fn ($r) => $r->label.' '.$r->payload)->implode(' ');
    expect($rows)->not->toContain('Cafe Hop')->not->toContain('Dewi')->not->toContain('private-menu')->not->toContain(QC_PASS)
        ->and(QrCode::orderBy('created_at')->first()->label)->toBe('Cafe Hop Lt 2');
    // a rename seals the new label too
    $id = QrCode::first()->id;
    $this->actingAs($user)->patch('/qr-codes/'.$id, ['label' => 'Renamed Lobby Wi-Fi'])->assertRedirect();
    expect((string) DB::table('qr_codes')->where('id', $id)->value('label'))->not->toContain('Lobby');
});

test('a row that cannot be opened is listed with a Delete button and does not break the page, the export or the rest', function () {
    $user = User::factory()->create();
    $good = qcSave($user, ['label' => 'Still fine']);
    // a row sealed with another key, as after an APP_KEY change without APP_PREVIOUS_KEYS
    $bad = (string) Str::ulid();
    DB::table('qr_codes')->insert(['id' => $bad, 'user_id' => $user->id, 'type' => 'wifi', 'label' => 'not-a-sealed-value', 'payload' => 'eyJpdiI6ImJhZCJ9', 'style' => json_encode(qcWifi()['style']), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

    $res = $this->actingAs($user)->get('/qr-codes')->assertOk();
    $res->assertSee('Still fine')->assertSee('This code cannot be opened any more. You can delete it.');
    expect($res->getContent())->toContain('data-qc-unreadable')->toContain(route('qr-codes.destroy', $bad));
    // it cannot be renamed, but it can be deleted
    $this->actingAs($user)->patch('/qr-codes/'.$bad, ['label' => 'x'])->assertNotFound();
    $this->actingAs($user)->delete('/qr-codes/'.$bad)->assertRedirect(route('qr-codes.index'));
    expect(QrCode::count())->toBe(1)->and(QrCode::first()->id)->toBe($good->id);
});

test('a refused form never puts what a code carries into the session as old input', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/qr-codes')->post('/qr-codes', ['type' => 'wifi', 'label' => 'Secret net', 'payload' => 'WIFI:T:WPA;S:Secret net;P:'.QC_PASS.';;', 'style' => ['fg' => 'not-a-colour']])
        ->assertSessionHasErrors();

    expect(session()->hasOldInput('payload'))->toBeFalse()->and(session()->hasOldInput('label'))->toBeFalse()
        ->and(json_encode(session()->all()))->not->toContain(QC_PASS)->not->toContain('Secret net');
});

test('two saves at the same moment cannot both pass the cap: the count and the write sit under one lock', function () {
    $user = User::factory()->create();
    foreach (range(1, QrCode::MAX_PER_USER - 1) as $i) {
        qcSave($user, ['label' => "Code $i"]);
    }
    // another request is saving for this account right now: this one waits, then gives up instead of racing past the cap
    $held = Cache::lock('qr-codes:'.$user->id, 10);
    expect($held->get())->toBeTrue();
    $this->actingAs($user)->postJson('/qr-codes', qcWifi())->assertStatus(429);
    expect(QrCode::where('user_id', $user->id)->count())->toBe(QrCode::MAX_PER_USER - 1);
    $held->release();

    $this->actingAs($user)->postJson('/qr-codes', qcWifi())->assertCreated();
    $this->actingAs($user)->postJson('/qr-codes', qcWifi())->assertStatus(422);
    expect(QrCode::where('user_id', $user->id)->count())->toBe(QrCode::MAX_PER_USER);
});

test('a label or content that holds </script> cannot end the data block of the page', function () {
    $user = User::factory()->create();
    $evil = '</script><script>window.pwned=1</script>';
    qcSave($user, ['type' => 'text', 'label' => $evil, 'payload' => 'https://example.com/'.$evil]);

    $html = $this->actingAs($user)->get('/qr-codes')->assertOk()->getContent();
    preg_match('~<script type="application/json" id="qc-data">(.*?)</script>~s', $html, $block);

    expect($block)->not->toBeEmpty()
        ->and($block[1])->toContain('\\u003C')->not->toContain('<')->not->toContain('>')
        ->and($html)->not->toContain('window.pwned=1</script>')
        ->and(json_decode($block[1], true)['saved'][0]['label'])->toBe($evil);
    // and as text on the page it is escaped
    expect($html)->not->toContain('<script>window.pwned');
});
