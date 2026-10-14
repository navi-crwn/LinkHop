<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads free, keyless threat feeds into storage/app/threat-feeds/:
 * URLhaus online malware URLs and the OpenPhish community phishing feed.
 * LinkSafety matches exact URLs (not hosts) so shared platforms aren't blocked.
 */
class UpdateThreatFeeds extends Command
{
    protected $signature = 'abuse:feeds';

    protected $description = 'Download URLhaus and OpenPhish threat feeds';

    public const FEEDS = [
        'malware' => 'https://urlhaus.abuse.ch/downloads/text_online/',
        'phishing' => 'https://openphish.com/feed.txt',
    ];

    public function handle(): int
    {
        $dir = storage_path('app/threat-feeds');
        File::ensureDirectoryExists($dir);
        $failed = 0;
        foreach (self::FEEDS as $category => $url) {
            try {
                $body = Http::timeout(60)->get($url)->throw()->body();
            } catch (\Throwable $e) {
                $this->warn("{$category}: {$e->getMessage()}");
                $failed++;

                continue; // keep the previous file
            }
            $urls = collect(preg_split('/\R/', $body))
                ->map(fn ($line) => trim($line))
                ->filter(fn ($line) => $line !== '' && ! str_starts_with($line, '#'))
                ->map(fn ($line) => self::normalize($line))
                ->unique();
            File::put("{$dir}/{$category}.txt", $urls->implode("\n"));
            $this->info("{$category}: {$urls->count()} URLs");
        }

        return $failed === count(self::FEEDS) ? self::FAILURE : self::SUCCESS;
    }

    public static function normalize(string $url): string
    {
        return rtrim(strtolower(preg_replace('#^https?://(www\.)?#i', '', trim($url))), '/');
    }
}
