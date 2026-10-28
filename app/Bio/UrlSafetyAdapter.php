<?php

namespace App\Bio;

use Hop\Bio\Contracts\UrlSafety;
use Hop\Safety\LinkSafety;

/** The hop/bio UrlSafety contract, answered by the family's one LinkSafety (hop/safety). */
class UrlSafetyAdapter implements UrlSafety
{
    public function __construct(private readonly LinkSafety $safety) {}

    public function rejectionReason(?string $url, string $text = '', array $schemes = self::WEB_SCHEMES): ?string
    {
        return $this->safety->rejectionReason($url, $text, $schemes);
    }

    public function assess(?string $url, string $text = '', bool $remote = false): array
    {
        return $this->safety->assess($url, $text, $remote);
    }

    public function host(?string $url): ?string
    {
        return $this->safety->host($url);
    }

    public function scheme(?string $url): ?string
    {
        return $this->safety->scheme($url);
    }
}
