<?php

namespace Hop\Bio\Contracts;

/** Device, browser and bot detection for bio click analytics. Bound to the host app's UserAgentService. */
interface UserAgentParser
{
    /** @return array{device: string, browser: string, os: string, bot: bool} */
    public function parse(?string $userAgent): array;
}
