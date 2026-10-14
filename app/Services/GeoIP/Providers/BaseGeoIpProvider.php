<?php

namespace App\Services\GeoIP\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseGeoIpProvider implements GeoIpProviderInterface
{
    // Short: lookups can happen while a visitor waits (login, bio page).
    protected int $timeout = 2;

    protected function makeRequest(string $url, array $params = []): ?array
    {
        try {
            $response = Http::timeout($this->timeout)->get($url, $params);
            if ($response->successful()) {
                return $response->json();
            }
            // Host only: the URL contains the visitor's IP.
            Log::warning("{$this->getName()} request failed", [
                'host' => parse_url($url, PHP_URL_HOST),
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning("{$this->getName()} request error", [
                'host' => parse_url($url, PHP_URL_HOST),
                'error' => str_replace($url, '[url]', $e->getMessage()),
            ]);

            return null;
        }
    }

    public function details(string $ip): array
    {
        $data = $this->lookup($ip);
        if (! $data) {
            return [
                'country' => null,
                'country_name' => null,
                'city' => null,
                'region' => null,
                'provider' => null,
            ];
        }

        return $this->normalize($data);
    }

    abstract protected function normalize(array $data): array;

    public function isAvailable(): bool
    {
        return true;
    }
}
