<?php

namespace Hop\Bio\Contracts;

/**
 * Judges URLs and text that people put on a bio page (blacklists, dangerous
 * schemes, spam signals). Bound to the host app's LinkSafety.
 */
interface UrlSafety
{
    public const WEB_SCHEMES = ['http', 'https'];

    public const BIO_SCHEMES = ['http', 'https', 'mailto', 'tel', 'sms'];

    /** Why the URL (and the text around it) may not be published, or null when it may. */
    public function rejectionReason(?string $url, string $text = '', array $schemes = self::WEB_SCHEMES): ?string;

    /**
     * Risk assessment of a URL and/or text.
     *
     * @return array{score: int, signals: array<int, string>}
     */
    public function assess(?string $url, string $text = '', bool $remote = false): array;

    public function host(?string $url): ?string;

    public function scheme(?string $url): ?string;
}
