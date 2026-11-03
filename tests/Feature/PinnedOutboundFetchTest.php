<?php

use App\Models\User;
use Hop\Safety\UrlGuard;

/** A guard that resolves from a fixed table. `$allowLoopback` lets the table point at 127.0.0.1 for the local test server. */
function tableGuard(array $answers, bool $allowLoopback = false): UrlGuard
{
    return new class($answers, $allowLoopback) extends UrlGuard
    {
        public function __construct(private array $answers, private bool $allowLoopback) {}

        protected function resolveHost(string $host): array
        {
            return $this->answers[$host] ?? [];
        }

        protected function isPublicIp(string $ip): bool
        {
            return ($this->allowLoopback && $ip === '127.0.0.1') || parent::isPublicIp($ip);
        }
    };
}

/** @return array{0: int, 1: resource, 2: string} port, process, router file */
function startPageServer(string $body): array
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $router = tempnam(sys_get_temp_dir(), 'router').'.php';
    file_put_contents($router, '<?php echo '.var_export($body, true).';');
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 50; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            break;
        }
        usleep(100000);
    }

    return [$port, $process, $router];
}

function stopPageServer(array $server): void
{
    proc_terminate($server[1]);
    proc_close($server[1]);
    @unlink($server[2]);
}

const PAGE = '<html><head><title>Pinned title</title><meta property="og:title" content="Pinned OG"></head><body></body></html>';

test('the Open Graph lookup connects to the address that was checked, not to a second DNS answer', function () {
    $server = startPageServer(PAGE);
    try {
        // site.example does not exist in DNS: the page can only be reached through the pinned address
        app()->instance(UrlGuard::class, tableGuard(['site.example' => ['127.0.0.1']], allowLoopback: true));
        $this->actingAs(User::factory()->create())
            ->postJson('/links/fetch-og', ['url' => 'http://site.example:'.$server[0].'/page'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Pinned OG');
    } finally {
        stopPageServer($server);
    }
});

test('the title lookup connects to the address that was checked, not to a second DNS answer', function () {
    $server = startPageServer(PAGE);
    try {
        app()->instance(UrlGuard::class, tableGuard(['site.example' => ['127.0.0.1']], allowLoopback: true));
        $this->actingAs(User::factory()->create())
            ->postJson('/api/utils/fetch-title', ['url' => 'http://site.example:'.$server[0].'/page'])
            ->assertOk()
            ->assertJsonPath('title', 'Pinned title');
    } finally {
        stopPageServer($server);
    }
});

test('the Open Graph and title lookups still refuse an internal address', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links/fetch-og', ['url' => 'http://127.0.0.1/admin'])->assertStatus(422);
    $this->actingAs($user)->postJson('/api/utils/fetch-title', ['url' => 'http://127.0.0.1/admin'])->assertStatus(422);
});
