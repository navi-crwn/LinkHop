<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
| scripts/check-string-casts.sh finds request values that turn "?x[]=1" into "Array to string conversion". The real code
| passes it, and code that would give a 500 does not.
*/

function casts(array $files = []): Illuminate\Contracts\Process\ProcessResult
{
    $dir = sys_get_temp_dir().'/casts-'.uniqid();
    File::makeDirectory($dir);
    foreach ($files as $name => $code) {
        File::put("{$dir}/{$name}", $code);
    }
    $result = Process::path(base_path())->env($files === [] ? [] : ['CHECK_DIRS' => $dir])->run(['bash', 'scripts/check-string-casts.sh', '--strict']);
    File::deleteDirectory($dir);

    return $result;
}

it('passes on the code as it is', function () {
    $result = casts();
    expect($result->exitCode())->toBe(0, $result->output());
});

it('finds a cast and an unvalidated string()', function (string $code) {
    expect(casts(['A.php' => "<?php {$code}"])->exitCode())->toBe(1);
})->with([
    '(string) $request->query("q");',
    '(string) $request->input("q");',
    '$request->string("q")->trim();',
    '$request->str("q");',
    'request()->string("q");',
]);

it('leaves the safe forms alone', function (string $code) {
    expect(casts(['A.php' => "<?php {$code}"])->exitCode())->toBe(0);
})->with(['$this->textInput($request, "q");', 'is_string($request->input("q"));', '$request->integer("hours");']);
