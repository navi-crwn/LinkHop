<?php

namespace App\Services\GeoIP\Providers;

use GeoIp2\Database\Reader;

/**
 * Offline lookups from local .mmdb files (DB-IP Lite or MaxMind GeoLite2).
 * No quota, no latency, visitor IPs never leave the server.
 * Files are downloaded by `php artisan geolite:update`.
 */
class LocalMmdbProvider extends BaseGeoIpProvider
{
    private static array $readers = [];

    public function getName(): string
    {
        return 'LocalMmdb';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getDailyLimit(): int
    {
        return PHP_INT_MAX;
    }

    public function getMonthlyLimit(): int
    {
        return PHP_INT_MAX;
    }

    public function isAvailable(): bool
    {
        return is_file((string) config('services.geolite.city_db'));
    }

    public function lookup(string $ip): ?array
    {
        try {
            $city = $this->reader(config('services.geolite.city_db'))->city($ip);
        } catch (\Throwable) {
            return null; // not found / private IP: fall through to the next provider
        }
        $isp = null;
        if (is_file((string) config('services.geolite.asn_db'))) {
            try {
                $isp = $this->reader(config('services.geolite.asn_db'))->asn($ip)->autonomousSystemOrganization;
            } catch (\Throwable) {
            }
        }

        return [
            'countryCode' => $city->country->isoCode,
            'country' => $city->country->name,
            'city' => $city->city->name,
            'regionName' => $city->mostSpecificSubdivision->name,
            'isp' => $isp,
        ];
    }

    protected function normalize(array $data): array
    {
        return [
            'country' => $data['countryCode'] ?? null,
            'country_name' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['regionName'] ?? null,
            'provider' => $data['isp'] ?? null,
        ];
    }

    private function reader(string $path): Reader
    {
        return self::$readers[$path] ??= new Reader($path);
    }
}
