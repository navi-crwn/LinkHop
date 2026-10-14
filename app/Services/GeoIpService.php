<?php

namespace App\Services;

use App\Services\GeoIP\Providers\AbstractApiProvider;
use App\Services\GeoIP\Providers\FreeIpApiProvider;
use App\Services\GeoIP\Providers\GeoIpProviderInterface;
use App\Services\GeoIP\Providers\IpApiCoProvider;
use App\Services\GeoIP\Providers\IpApiProvider;
use App\Services\GeoIP\Providers\IpInfoProvider;
use App\Services\GeoIP\Providers\LocalMmdbProvider;
use App\Services\GeoIP\QuotaManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeoIpService
{
    private const MAX_EXTERNAL_PER_LOOKUP = 2;

    private const PROVIDER_COOLDOWN_MINUTES = 10;

    protected array $providers = [];

    protected QuotaManager $quotaManager;

    public function __construct()
    {
        $this->quotaManager = new QuotaManager;
        $this->providers = [
            new LocalMmdbProvider,
            new FreeIpApiProvider,
            new IpApiProvider,
            new IpApiCoProvider,
            new IpInfoProvider,
            new AbstractApiProvider,
        ];
        usort($this->providers, fn ($a, $b) => $a->getPriority() <=> $b->getPriority());
    }

    protected function getAvailableProvider(): ?GeoIpProviderInterface
    {
        foreach ($this->providers as $provider) {
            if (! $provider->isAvailable()) {
                continue;
            }
            if ($this->quotaManager->hasAvailableQuota(
                $provider->getName(),
                $provider->getDailyLimit(),
                $provider->getMonthlyLimit()
            )) {
                return $provider;
            }
        }
        Log::critical('All GeoIP providers exhausted or unavailable');

        return null;
    }

    protected function lookup(string $ip): ?array
    {
        $cacheKey = "geoip:data:{$ip}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null; // false = looked up recently, nothing found
        }
        // Private and reserved addresses are never in any database; do not spend web API calls on them.
        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        $externalTried = 0;
        foreach ($this->providers as $provider) {
            if (! $provider->isAvailable() || Cache::has($this->downKey($provider))) {
                continue;
            }
            if (! $this->quotaManager->hasAvailableQuota(
                $provider->getName(),
                $provider->getDailyLimit(),
                $provider->getMonthlyLimit()
            )) {
                continue;
            }
            // A new IP must not wait on a chain of slow web APIs: try at most two of them.
            $external = ! $provider instanceof LocalMmdbProvider;
            if ($external && (! $public || $externalTried++ >= self::MAX_EXTERNAL_PER_LOOKUP)) {
                break;
            }
            $result = $provider->lookup($ip);
            if ($result !== null) {
                $this->quotaManager->increment($provider->getName());
                Cache::put($cacheKey, $result, now()->addDay());

                return $result;
            }
            if ($external) {
                // Skip a failing web API for a while instead of paying its timeout on every new IP.
                Cache::put($this->downKey($provider), true, now()->addMinutes(self::PROVIDER_COOLDOWN_MINUTES));
                // No IP in the log: it is personal data and the policy promises hashes only.
                Log::warning('GeoIP provider failed, trying next', ['provider' => $provider->getName()]);
            }
        }
        Log::notice('No GeoIP provider could resolve an IP');
        Cache::put($cacheKey, false, now()->addHour());

        return null;
    }

    private function downKey(GeoIpProviderInterface $provider): string
    {
        return 'geoip:down:'.$provider->getName();
    }

    public function country(string $ip): ?string
    {
        $provider = $this->getAvailableProvider();
        if (! $provider) {
            return null;
        }
        $data = $this->lookup($ip);

        return $data['countryCode'] ?? $data['country_code'] ?? $data['country'] ?? null;
    }

    public function city(string $ip): ?string
    {
        $provider = $this->getAvailableProvider();
        if (! $provider) {
            return null;
        }
        $data = $this->lookup($ip);

        return $data['city'] ?? $data['cityName'] ?? null;
    }

    public function provider(string $ip): ?string
    {
        $provider = $this->getAvailableProvider();
        if (! $provider) {
            return null;
        }
        $data = $this->lookup($ip);

        return $data['isp'] ?? $data['org'] ?? null;
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

        return [
            'country' => $data['countryCode'] ?? $data['country_code'] ?? $data['country'] ?? null,
            'country_name' => $data['country'] ?? $data['countryName'] ?? $data['country_name'] ?? null,
            'city' => $data['city'] ?? $data['cityName'] ?? null,
            'region' => $data['regionName'] ?? $data['region'] ?? $data['region_name'] ?? null,
            'provider' => $data['isp'] ?? $data['org'] ?? $data['connection']['isp_name'] ?? null,
        ];
    }

    public function getQuotaStats(): array
    {
        return $this->quotaManager->getStats();
    }

    public function getProviders(): array
    {
        return array_map(function ($provider) {
            return [
                'name' => $provider->getName(),
                'priority' => $provider->getPriority(),
                'available' => $provider->isAvailable(),
                'daily_limit' => $provider->getDailyLimit(),
                'monthly_limit' => $provider->getMonthlyLimit(),
                'daily_usage' => $this->quotaManager->getUsage($provider->getName(), 'daily'),
                'monthly_usage' => $this->quotaManager->getUsage($provider->getName(), 'monthly'),
            ];
        }, $this->providers);
    }
}
