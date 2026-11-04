<?php

use App\Logging\ScrubNoteKeys;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
| A database error carries the SQL with its values. If one of the values is a NoteHop address with its key in the
| fragment (a free-text field, a row saved before the fix), that text must not reach laravel.log or a debug page.
| Made-up key.
*/

const LK_KEY = 'FAKEKEYlog777';

/** The QueryException of a duplicate slug, with a NoteHop address and its key as one of the values. */
function lkQueryException(): QueryException
{
    $row = ['user_id' => null, 'slug' => 'dupslug', 'target_url' => 'https://note.hel.ink/Ab3dEf#k='.LK_KEY.'&a='.LK_KEY, 'status' => 'active'];
    DB::table('links')->insert($row);
    try {
        DB::table('links')->insert($row);
    } catch (QueryException $e) {
        return $e;
    }
    throw new RuntimeException('the second insert should have failed');
}

it('keeps the key out of laravel.log when a query fails', function () {
    $path = tempnam(sys_get_temp_dir(), 'lklog');
    config(['logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    $e = lkQueryException();

    Log::channel('single')->error($e->getMessage(), ['exception' => $e]);

    $log = (string) file_get_contents($path);
    @unlink($path);
    expect($log)->toContain('UNIQUE constraint failed')
        ->and($log)->toContain('https://note.hel.ink/Ab3dEf')
        ->and($log)->not->toContain(LK_KEY);
});

it('keeps the scrubbing on every channel that writes text', function () {
    foreach (['single', 'daily', 'stderr', 'errorlog', 'syslog'] as $channel) {
        expect((array) config("logging.channels.{$channel}.tap"))->toContain(ScrubNoteKeys::class);
    }
});

it('does not print the key on a debug error page, in HTML or JSON', function () {
    // the debug page is a big render, and the full suite is already near the default limit
    ini_set('memory_limit', '512M');
    config(['app.debug' => true]);
    Route::get('/__lk-boom', fn () => throw lkQueryException());
    Route::get('/__lk-boom-ok', fn () => throw new RuntimeException('plain failure'));

    $html = $this->get('/__lk-boom');
    $json = $this->getJson('/__lk-boom');
    $plain = $this->getJson('/__lk-boom-ok');

    expect($html->status())->toBe(500)
        ->and($html->getContent())->not->toContain(LK_KEY)
        ->and($json->status())->toBe(500)
        ->and($json->getContent())->not->toContain(LK_KEY)
        // a failure without a key still gets the debug page
        ->and($plain->getContent())->toContain('plain failure');
});
