<?php

namespace App\Support;

use App\Models\Link;
use App\Models\LinkClick;

/**
 * A/B split by weight: 2 to 5 addresses, each with a weight. The visitor's keyed hash (see VisitorHash) picks one,
 * so the same visitor keeps seeing the same address. Nothing about the visitor is stored for this.
 */
class AbSplit
{
    public const MIN = 2;

    public const MAX = 5;

    public const MAX_WEIGHT = 1000;

    /**
     * Clean up what a form or the API sent: trimmed, a scheme added, empty rows dropped, a missing weight is 1.
     * Anything that is not a list of rows is returned as it is so the validator can refuse it.
     */
    public static function tidy(mixed $variants): mixed
    {
        if (! is_array($variants)) {
            return blank($variants) ? [] : $variants;
        }
        $out = [];
        foreach ($variants as $row) {
            if (! is_array($row)) {
                $out[] = $row;

                continue;
            }
            $url = LinkRouting::address($row['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $weight = $row['weight'] ?? null;
            $weight = is_string($weight) ? trim($weight) : $weight;
            $out[] = [
                'url' => $url,
                'weight' => $weight === null || $weight === '' ? 1 : (is_numeric($weight) && (string) (int) $weight === (string) $weight ? (int) $weight : $weight),
            ];
        }

        return $out;
    }

    /**
     * Problems with the (tidied) variants, keyed by field, in the visitor-facing words of the form.
     *
     * @return array<string, string>
     */
    public static function problems(mixed $variants): array
    {
        if (! is_array($variants) || $variants === []) {
            return [];
        }
        $problems = [];
        if (count($variants) < self::MIN) {
            $problems['ab_variants'] = __('A split test needs at least two addresses.');
        }
        foreach ($variants as $i => $row) {
            if (! is_array($row)) {
                $problems['ab_variants'] = __('A split test needs at least two addresses.');

                continue;
            }
            $n = $i + 1;
            if (! is_int($row['weight'] ?? null) || $row['weight'] < 1 || $row['weight'] > self::MAX_WEIGHT) {
                $problems["ab_variants.$i.weight"] = __('Variant :n: the weight must be a whole number from 1 to :max.', ['n' => $n, 'max' => self::MAX_WEIGHT]);
            }
            if ($problem = LinkRouting::addressProblem((string) ($row['url'] ?? ''))) {
                $problems["ab_variants.$i.url"] = __('Variant :n: :problem', ['n' => $n, 'problem' => __($problem)]);
            }
        }

        return $problems;
    }

    /** What is saved: the tidied rows, or null for no split. */
    public static function store(mixed $variants): ?array
    {
        $variants = self::tidy($variants);

        return is_array($variants) && $variants !== []
            ? array_map(fn ($v) => ['url' => (string) $v['url'], 'weight' => (int) $v['weight']], array_values($variants))
            : null;
    }

    /** The saved variants, only the well-formed ones, in order. @return array<int, array{url: string, weight: int}> */
    public static function variants(Link $link): array
    {
        $out = [];
        foreach ((array) $link->ab_variants as $v) {
            if (is_array($v) && preg_match('#^https?://#i', (string) ($v['url'] ?? '')) && (int) ($v['weight'] ?? 0) > 0) {
                $out[] = ['url' => (string) $v['url'], 'weight' => (int) $v['weight']];
            }
        }

        return count($out) >= self::MIN ? $out : [];
    }

    /**
     * The position (0-based) of the variant for a visitor. The same visitor on the same link always lands on the same one
     * while the variants do not change; with no visitor hash (no address known) the pick is random.
     *
     * @param  array<int, array{url: string, weight: int}>  $variants
     */
    public static function pick(array $variants, int $linkId, ?string $visitor): int
    {
        $total = array_sum(array_column($variants, 'weight'));
        $point = $visitor === null
            ? random_int(0, $total - 1)
            : hexdec(substr(hash('sha256', $linkId.'|'.$visitor), 0, 12)) % $total;
        foreach ($variants as $i => $variant) {
            $point -= $variant['weight'];
            if ($point < 0) {
                return $i;
            }
        }

        return array_key_last($variants);
    }

    /** Clicks per variant position (1-based), for the analytics and the edit page. @return array<int, int> */
    public static function clicks(Link $link): array
    {
        if (! self::variants($link)) {
            return [];
        }

        return LinkClick::where('link_id', $link->id)->whereNotNull('variant')
            ->selectRaw('variant, COUNT(*) as total')->groupBy('variant')->pluck('total', 'variant')
            ->map(fn ($n) => (int) $n)->all();
    }
}
