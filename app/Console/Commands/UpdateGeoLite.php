<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the local GeoIP databases used by LocalMmdbProvider.
 * Default: DB-IP Lite (free, no account, CC BY 4.0). With GEOLITE_LICENSE_KEY
 * set, MaxMind GeoLite2 is used instead.
 */
class UpdateGeoLite extends Command
{
    protected $signature = 'geolite:update';

    protected $description = 'Download local GeoIP databases (DB-IP Lite, or MaxMind GeoLite2 with a license key)';

    public function handle(): int
    {
        $targets = ['City' => config('services.geolite.city_db'), 'ASN' => config('services.geolite.asn_db')];
        $ok = true;
        foreach ($targets as $edition => $target) {
            File::ensureDirectoryExists(dirname($target));
            $ok = (config('services.geolite.license_key') ? $this->maxmind($edition, $target) : $this->dbip($edition, $target)) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function dbip(string $edition, string $target): bool
    {
        $name = strtolower($edition);
        // Published monthly; fall back to last month early in the month.
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $url = "https://download.db-ip.com/free/dbip-{$name}-lite-{$month->format('Y-m')}.mmdb.gz";
            $gz = storage_path("app/dbip-{$name}.mmdb.gz");
            $this->info("Downloading {$url}");
            try {
                if (! Http::timeout(300)->sink($gz)->get($url)->ok()) {
                    continue;
                }
            } catch (\Throwable $e) {
                $this->warn($e->getMessage());

                continue;
            }
            // Stream-decompress (the city file is ~130 MB unpacked).
            $in = gzopen($gz, 'rb');
            $out = fopen($target.'.tmp', 'wb');
            while (! gzeof($in)) {
                fwrite($out, gzread($in, 1 << 20));
            }
            gzclose($in);
            fclose($out);
            File::delete($gz);
            File::move($target.'.tmp', $target);
            $this->info("DB-IP {$edition} Lite updated.");

            return true;
        }
        $this->error("Failed to download DB-IP {$edition} Lite.");

        return false;
    }

    private function maxmind(string $edition, string $target): bool
    {
        $url = sprintf('https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-%s&license_key=%s&suffix=tar.gz', $edition, config('services.geolite.license_key'));
        $tempGz = storage_path("app/GeoLite2-{$edition}.tar.gz");
        $extractDir = storage_path("app/geoip_unpack_{$edition}");
        $this->info("Downloading GeoLite2-{$edition}...");
        if (! Http::timeout(300)->sink($tempGz)->get($url)->ok()) {
            $this->error("Failed to download GeoLite2-{$edition}.");

            return false;
        }
        File::deleteDirectory($extractDir);
        (new \PharData($tempGz))->extractTo($extractDir, null, true);
        $mmdb = collect(File::allFiles($extractDir))->first(fn ($file) => $file->getExtension() === 'mmdb');
        if ($mmdb) {
            File::copy($mmdb->getPathname(), $target);
        }
        File::delete($tempGz);
        File::deleteDirectory($extractDir);
        $mmdb ? $this->info("GeoLite2-{$edition} updated.") : $this->error('Could not locate mmdb file.');

        return (bool) $mmdb;
    }
}
