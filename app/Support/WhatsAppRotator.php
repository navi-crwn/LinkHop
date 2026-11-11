<?php

namespace App\Support;

use App\Models\Link;
use App\Models\LinkClick;
use Hop\Safety\LinkSafety;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * WhatsApp rotator for a short link (accounts only): up to 10 numbers, each with a weight and an optional prefilled
 * message. Every visit goes to https://wa.me/<number>?text=..., either in turn (round_robin) or by weight (weighted:
 * a fixed cycle as long as the weights add up to, so the shares are exact, not random). Shared by the dashboard form
 * and the redirect, like LinkRouting. The names follow BioHop's rotator block (mode, numbers, number, weight).
 *
 * The turn comes from links.wa_cursor, moved by one UPDATE ... + 1 inside a transaction, so concurrent visits
 * never take the same turn. Only a link with wa_mode set ever reads or writes it.
 */
class WhatsAppRotator
{
    public const MODES = ['round_robin', 'weighted'];

    public const MAX_NUMBERS = 10;

    public const MAX_WEIGHT = 100;

    public const MIN_DIGITS = 8;

    public const MAX_DIGITS = 15;

    public const MAX_MESSAGE = 500;

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'wa_mode' => ['nullable', 'string', 'max:16'],
            'wa_numbers' => ['nullable', 'array', 'max:'.self::MAX_NUMBERS],
            'wa_numbers.*' => ['array'],
            // Listed so validated() keeps them; the values are checked in problems().
            'wa_numbers.*.number' => ['nullable'],
            'wa_numbers.*.weight' => ['nullable'],
            'wa_numbers.*.message' => ['nullable'],
        ];
    }

    /**
     * A number as digits with the country code and no "+" (null when it cannot be one). Spaces, dots, dashes and
     * brackets are allowed as typed; a leading + or 00 is dropped. 8 to 15 digits, not starting with 0.
     */
    public static function normalize(mixed $raw): ?string
    {
        if (! is_string($raw) && ! is_int($raw)) {
            return null;
        }
        $raw = trim((string) $raw);
        if ($raw === '' || ! preg_match('/^\+?[0-9 .()\-]+$/', $raw)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^[1-9][0-9]{'.(self::MIN_DIGITS - 1).','.(self::MAX_DIGITS - 1).'}$/', $digits) ? $digits : null;
    }

    /** The wa.me address of a number with its message; the text is URL-encoded, never added raw. */
    public static function url(string $number, string $message = ''): string
    {
        return 'https://wa.me/'.$number.($message !== '' ? '?text='.rawurlencode($message) : '');
    }

    /**
     * Tidy the two fields of a request's input before validation; keys that were not sent stay absent. Rows nobody
     * filled in are dropped. A number that normalises is replaced by its digits; one that does not stays as typed so
     * the validator can name it.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function tidy(array $input): array
    {
        $out = [];
        if (array_key_exists('wa_mode', $input)) {
            $mode = is_string($input['wa_mode']) ? trim($input['wa_mode']) : $input['wa_mode'];
            $out['wa_mode'] = $mode === '' ? null : $mode;
        }
        if (array_key_exists('wa_numbers', $input)) {
            $rows = $input['wa_numbers'];
            if (! is_array($rows)) {
                $out['wa_numbers'] = blank($rows) ? [] : $rows;
            } else {
                $list = [];
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        $list[] = $row;

                        continue;
                    }
                    $raw = $row['number'] ?? '';
                    $raw = is_scalar($raw) ? trim((string) $raw) : $raw;
                    $weight = $row['weight'] ?? null;
                    $weight = is_string($weight) ? trim($weight) : $weight;
                    $message = $row['message'] ?? '';
                    $message = is_scalar($message) ? trim(str_replace("\r\n", "\n", (string) $message)) : $message;
                    if ($raw === '' && $message === '') {
                        continue;
                    }
                    $list[] = [
                        'number' => is_string($raw) ? (self::normalize($raw) ?? $raw) : $raw,
                        'weight' => $weight === null || $weight === '' ? 1 : (is_numeric($weight) && (string) (int) $weight === (string) $weight ? (int) $weight : $weight),
                        'message' => $message,
                    ];
                }
                $out['wa_numbers'] = $list;
            }
        }

        return $out;
    }

    /** Add the problems the plain rules cannot say to a validator. */
    public static function check(Validator $validator, array $input): void
    {
        $mode = $input['wa_mode'] ?? null;
        $rows = $input['wa_numbers'] ?? [];
        if ($mode === null && (! is_array($rows) || $rows === [])) {
            return;
        }
        foreach (self::problems($mode, $rows) as $field => $message) {
            if (! $validator->errors()->has($field)) {
                $validator->errors()->add($field, $message);
            }
        }
    }

    /**
     * Problems with the (tidied) mode and rows, keyed by field, in the visitor-facing words of the form.
     *
     * @return array<string, string>
     */
    public static function problems(mixed $mode, mixed $rows): array
    {
        $problems = [];
        if ($mode === null) {
            return $problems;
        }
        if (! in_array($mode, self::MODES, true)) {
            return ['wa_mode' => __('Pick how the numbers take turns from the list.')];
        }
        if (! is_array($rows) || $rows === []) {
            return ['wa_numbers' => __('Add at least one WhatsApp number.')];
        }
        $seen = [];
        foreach (array_values($rows) as $i => $row) {
            $n = $i + 1;
            if (! is_array($row)) {
                $problems["wa_numbers.$i"] = __('Number :n is not valid.', ['n' => $n]);

                continue;
            }
            $number = self::normalize($row['number'] ?? null);
            if ($number === null) {
                $problems["wa_numbers.$i.number"] = __('Number :n: ":number" is not a WhatsApp number. Use digits with the country code, :min to :max digits, for example 15555550101.', [
                    'n' => $n, 'number' => mb_substr((string) (is_scalar($row['number'] ?? null) ? $row['number'] : ''), 0, 30), 'min' => self::MIN_DIGITS, 'max' => self::MAX_DIGITS,
                ]);
            } elseif (isset($seen[$number])) {
                $problems["wa_numbers.$i.number"] = __('Number :n is already in the list.', ['n' => $n]);
            } else {
                $seen[$number] = true;
            }
            $weight = $row['weight'] ?? 1;
            if (! is_int($weight) || $weight < 1 || $weight > self::MAX_WEIGHT) {
                $problems["wa_numbers.$i.weight"] = __('Number :n: the weight must be a whole number from 1 to :max.', ['n' => $n, 'max' => self::MAX_WEIGHT]);
            }
            $message = $row['message'] ?? '';
            if (! is_string($message)) {
                $problems["wa_numbers.$i.message"] = __('Number :n: the message must be text.', ['n' => $n]);
            } elseif (mb_strlen($message) > self::MAX_MESSAGE) {
                $problems["wa_numbers.$i.message"] = __('Number :n: the message can be :max characters at most.', ['n' => $n, 'max' => self::MAX_MESSAGE]);
            } elseif (preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $message)) {
                $problems["wa_numbers.$i.message"] = __('Number :n: the message cannot contain control characters.', ['n' => $n]);
            } elseif ($number !== null) {
                // The same checks a destination gets, on the address a visitor would really be sent to, and the words in the text.
                $reason = app(LinkSafety::class)->rejectionReason(self::url($number, $message), $message);
                if ($reason) {
                    $problems["wa_numbers.$i.message"] = __('Number :n: :problem', ['n' => $n, 'problem' => __($reason)]);
                } elseif (app(EncryptedNoteLink::class)->holdsKey($message)) {
                    $problems["wa_numbers.$i.message"] = app(EncryptedNoteLink::class)->message();
                }
            }
        }

        return $problems;
    }

    /**
     * The two link columns as they are saved. Each number keeps the id it had (matched by number), new ones get the
     * next free id, so per-number counts stay with their number when the list is edited.
     *
     * @return array{wa_mode: ?string, wa_numbers: ?array}
     */
    public static function columns(array $data, ?Link $link = null): array
    {
        $mode = $data['wa_mode'] ?? null;
        $rows = $data['wa_numbers'] ?? [];
        if (! in_array($mode, self::MODES, true) || ! is_array($rows) || $rows === []) {
            return ['wa_mode' => null, 'wa_numbers' => null];
        }
        $old = collect((array) $link?->wa_numbers)->keyBy('number');
        $clean = [];
        foreach ($rows as $row) {
            if (($number = self::normalize($row['number'] ?? null)) !== null) {
                $clean[] = [$number, $row];
            }
        }
        // A number that stays keeps its id and its count. A new number takes the smallest id nobody in the list uses,
        // so ids never pass the list length (10): they always fit link_clicks.variant (an unsigned tiny integer that the
        // A/B split shares). A reused id must not inherit the clicks of the number that had it, so every new number
        // records `since`, and its count only includes clicks from then on (see clicks()).
        $used = [];
        foreach ($clean as [$number]) {
            if ($old->has($number)) {
                $used[(int) $old[$number]['id']] = true;
            }
        }
        $numbers = [];
        foreach ($clean as [$number, $row]) {
            if ($old->has($number)) {
                $id = (int) $old[$number]['id'];
                $since = (int) ($old[$number]['since'] ?? 0);
            } else {
                $id = 1;
                while (isset($used[$id])) {
                    $id++;
                }
                $used[$id] = true;
                $since = now()->timestamp;
            }
            $numbers[] = ['id' => $id, 'number' => $number, 'weight' => (int) ($row['weight'] ?? 1), 'message' => (string) ($row['message'] ?? ''), 'since' => $since];
        }

        return $numbers === [] ? ['wa_mode' => null, 'wa_numbers' => null] : ['wa_mode' => $mode, 'wa_numbers' => $numbers];
    }

    /** True when the link is a rotator (no query: the columns come with the cached row). */
    public static function active(Link $link): bool
    {
        return in_array($link->wa_mode, self::MODES, true) && ! empty($link->wa_numbers);
    }

    /**
     * Which number takes the turn: pure, so it can be tested without a database. $turn counts from 0.
     * round_robin walks the list; weighted walks a cycle as long as the weights add up to.
     *
     * @param  array<int, array{weight?: int}>  $numbers
     */
    public static function slot(array $numbers, string $mode, int $turn): int
    {
        $count = count($numbers);
        if ($count <= 1) {
            return 0;
        }
        if ($mode !== 'weighted') {
            return $turn % $count;
        }
        $total = 0;
        foreach ($numbers as $n) {
            $total += max(1, (int) ($n['weight'] ?? 1));
        }
        $point = $turn % $total;
        foreach (array_values($numbers) as $i => $n) {
            $point -= max(1, (int) ($n['weight'] ?? 1));
            if ($point < 0) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * The address and the number's id for this visit. A crawler or link-preview bot ($advance false) gets the first
     * number and does not use up a turn.
     *
     * @return array{url: string, id: int}
     */
    public static function pick(Link $link, bool $advance): array
    {
        $numbers = array_values((array) $link->wa_numbers);
        $turn = 0;
        if ($advance && count($numbers) > 1) {
            $turn = DB::transaction(function () use ($link) {
                DB::table('links')->where('id', $link->id)->increment('wa_cursor');

                return (int) DB::table('links')->where('id', $link->id)->value('wa_cursor') - 1;
            });
        }
        $chosen = $numbers[self::slot($numbers, (string) $link->wa_mode, max(0, $turn))];

        return ['url' => self::url($chosen['number'], (string) ($chosen['message'] ?? '')), 'id' => (int) $chosen['id']];
    }

    /** Clicks per number id, for the link's statistics. @return array<int, int> */
    public static function clicks(Link $link): array
    {
        if (! self::active($link)) {
            return [];
        }

        // One count per number (at most 10, on the statistics page only): clicks from before the number was added (an
        // old A/B split's variants, a removed number that had the same id) are not its clicks.
        $out = [];
        foreach ((array) $link->wa_numbers as $n) {
            $since = (int) ($n['since'] ?? 0);
            $out[(int) $n['id']] = LinkClick::where('link_id', $link->id)->where('variant', $n['id'])
                ->when($since > 0, fn ($q) => $q->where('clicked_at', '>=', Carbon::createFromTimestamp($since, config('app.timezone'))))
                ->count();
        }

        return $out;
    }
}
