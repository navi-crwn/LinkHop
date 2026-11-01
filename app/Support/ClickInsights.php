<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * What a set of clicks says beyond how many there are: distinct visitors, QR scans against direct clicks, and the
 * apps whose built-in browsers opened the link. Takes a LinkClick query already limited to the links and period.
 */
class ClickInsights
{
    /**
     * @return array{
     *     unique: int,
     *     sources: array{direct: int, qr: int, other: int},
     *     in_app: array<string, int>,
     *     in_app_total: int
     * }
     */
    public static function summarize(Builder $clicks): array
    {
        $bySource = (clone $clicks)->selectRaw('source, COUNT(*) as total')->groupBy('source')->pluck('total', 'source');
        $direct = (int) ($bySource[ClickSource::DIRECT] ?? 0);
        $qr = (int) ($bySource[ClickSource::QR] ?? 0);
        $inApp = (clone $clicks)->whereNotNull('in_app')
            ->selectRaw('in_app, COUNT(*) as total')->groupBy('in_app')->orderByDesc('total')->limit(12)
            ->pluck('total', 'in_app')->map(fn ($n) => (int) $n)->all();

        return [
            // Distinct per day: someone who comes back tomorrow is counted again (the hash rotates, see VisitorHash::daily).
            'unique' => (int) (clone $clicks)->whereNotNull('visitor_hash')->distinct()->count('visitor_hash'),
            'sources' => [
                'direct' => $direct,
                'qr' => $qr,
                'other' => max(0, (int) $bySource->sum() - $direct - $qr),
            ],
            'in_app' => $inApp,
            'in_app_total' => (clone $clicks)->whereNotNull('in_app')->count(),
        ];
    }
}
