<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tells the owner about a 500, at most once per kind of error per hour. A kind is the exception class and the line that
 * threw it. The mail holds only those and the route's name: a message, a trace or an address can hold what a visitor
 * sent, and the details are in the server's log. It goes out after the visitor has the answer, and the count lives in
 * the file cache, which still works while the database is down.
 *
 * It goes to one address: the one set in Imperium, or admin@hel.ink on hel.ink's own hosts. Any other install sends
 * nothing until it sets one. The setting is a file for the same reason as the count.
 */
class ServerErrorAlert
{
    public const SETTING = 'errors.alert_email';

    private const STORE = 'file';

    private const FILE = 'server-error-alert-email.txt';

    private const HEL_INK_ADDRESS = 'admin@hel.ink';

    private const WINDOW_SECONDS = 3600;

    private const COUNT_SECONDS = 2592000;

    public static function afterResponse(Throwable $e, Request $request, int $status): void
    {
        if ($status !== 500 || ! in_array(config('app.env'), ['production', 'staging'], true)) {
            return;
        }
        $class = Str::before($e::class, "\0");
        $place = Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR).':'.$e->getLine();
        $route = $request->route();
        $routeName = is_object($route) ? ($route->getName() ?? $route->uri()) : 'none';

        // An application that serves more than one request keeps its terminating callbacks: this one counts once.
        $done = false;
        app()->terminating(function () use (&$done, $class, $place, $routeName) {
            if ($done) {
                return;
            }
            $done = true;
            try {
                if ($to = self::recipient()) {
                    self::send($to, $class, $place, (string) $routeName);
                }
            } catch (Throwable $failure) {
                Log::warning('The server error mail could not be sent', ['exception' => $failure::class]);
            }
        });
    }

    public static function recipient(): ?string
    {
        if ($stored = self::stored()) {
            return $stored;
        }
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === 'hel.ink' || str_ends_with($host, '.hel.ink') ? self::HEL_INK_ADDRESS : null;
    }

    public static function stored(): ?string
    {
        $address = trim((string) Storage::disk('local')->get(self::FILE));

        return $address !== '' ? $address : null;
    }

    /** An empty address clears the setting. False when it could not be written. */
    public static function store(string $address): bool
    {
        return (bool) Storage::disk('local')->put(self::FILE, $address);
    }

    public static function validAddress(mixed $value): bool
    {
        return is_string($value) && (trim($value) === '' || filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false);
    }

    private static function send(string $to, string $class, string $place, string $route): void
    {
        $cache = Cache::store(self::STORE);
        $key = 'server-error-alert:'.sha1($class.'|'.$place);
        if (! $cache->add($key, 1, self::WINDOW_SECONDS)) {
            $cache->add($key.':more', 0, self::COUNT_SECONDS);
            $cache->increment($key.':more');

            return;
        }
        $more = (int) $cache->pull($key.':more', 0);
        $name = (string) config('app.name');
        $lines = [
            "{$name} answered 500 at ".now()->toDateTimeString().' UTC.',
            '',
            "Error: {$class}",
            "Where: {$place}",
            "Route: {$route}",
            "Count: 1, and {$more} more since the last mail",
            '',
            'The next mail about this error comes an hour from now at the earliest. The details are in the log on the server.',
        ];

        Mail::raw(implode("\n", $lines), fn ($mail) => $mail->to($to)->subject("[{$name}] Server error: ".class_basename($class)));
    }
}
