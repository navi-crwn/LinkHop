<?php

namespace Hop\Bio\Contracts;

/** Country of a visitor's IP, for bio click analytics. Bound to the host app's GeoIpService. */
interface GeoLookup
{
    /** ISO 3166-1 alpha-2 code, or null when unknown. */
    public function country(string $ip): ?string;
}
