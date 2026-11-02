<?php

use App\Support\DestinationChecker;
use Hop\Safety\UrlGuard;
use Illuminate\Support\Facades\Http;

function checkerWith(array $answers): DestinationChecker
{
    return new DestinationChecker(new class($answers) extends UrlGuard
    {
        public function __construct(private array $answers) {}

        protected function resolveHost(string $host): array
        {
            return $this->answers[$host] ?? [];
        }
    });
}

test('the connection is pinned to the addresses that were checked', function () {
    $seen = new ArrayObject;
    Http::fake(function ($request, $options) use ($seen) {
        $seen[] = $options['curl'][CURLOPT_RESOLVE] ?? null;

        return Http::response('ok', 200);
    });

    $result = checkerWith(['site.example' => ['93.184.216.34']])->check('https://site.example/page');

    expect($result['state'])->toBe(DestinationChecker::OK);
    expect($seen[0])->toBe(['site.example:443:93.184.216.34']);
});

test('a private address is never contacted', function () {
    Http::fake();

    $result = checkerWith([])->check('http://127.0.0.1/admin');

    expect($result['state'])->toBe(DestinationChecker::SKIPPED);
    Http::assertNothingSent();
});
