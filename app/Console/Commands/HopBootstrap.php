<?php

namespace App\Console\Commands;

use App\Models\HopApp;
use Illuminate\Console\Command;

/**
 * One-time move from the old settings: the PIXELHOP_* / NOTEHOP_* / IMPERIUM_* keys
 * become rows in `apps` (the registry of apps that may call the signed admin API).
 * Roles and the owner account are the hub's (`php artisan hop:owner` there).
 * Safe to run again: it only adds what is missing.
 */
class HopBootstrap extends Command
{
    protected $signature = 'hop:bootstrap {--dry-run : Show what would change}';

    protected $description = 'Create the app registry (Imperium and friends) from the old .env settings';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        foreach ([
            'pixelhop' => ['name' => 'PixelHop', 'menu_label' => 'Images', 'icon' => 'image', 'callback_path' => '/auth/helink/callback',
                'start_path' => '/auth/helink', 'pages' => ['default' => '/dashboard', 'upload' => '/'], 'continue_page' => 'upload',
                'capabilities' => ['account_api'], 'sort_order' => 10],
            'notehop' => ['name' => 'NoteHop', 'menu_label' => 'Notes', 'icon' => 'note', 'callback_path' => '/auth/sso/callback',
                'start_path' => '/auth/sso', 'pages' => ['default' => '/dashboard', 'new' => '/'], 'continue_page' => 'default',
                'capabilities' => [], 'sort_order' => 20],
            // The control center: signs in through HOP ID like any app, but stays out of
            // the sidebar and may use the admin API.
            'imperium' => ['name' => 'Imperium', 'menu_label' => null, 'callback_path' => '/auth/sso/callback',
                'start_path' => '/auth/sso', 'pages' => ['default' => '/'], 'continue_page' => 'default',
                'capabilities' => ['admin_client'], 'sort_order' => 1, 'hidden' => true],
            // Imperium's second door (Cloudflare Access): a ticket may only return to one
            // URL, so the second hostname is its own registry entry with its own secret.
            'citadel' => ['name' => 'Imperium (citadel)', 'menu_label' => null, 'callback_path' => '/auth/sso/callback',
                'start_path' => '/auth/sso', 'pages' => ['default' => '/'], 'continue_page' => 'default',
                'capabilities' => [], 'sort_order' => 2, 'hidden' => true],
        ] as $slug => $row) {
            $url = (string) config("services.$slug.url");
            $secret = (string) config("services.$slug.sso_secret");
            if ($url === '' || strlen($secret) < 32) {
                $this->line("$slug: not configured in .env, skipped.");

                continue;
            }
            if (HopApp::query()->where('slug', $slug)->exists()) {
                $this->line("$slug: already registered.");

                continue;
            }
            $this->info("$slug: registering {$url}");
            $dry || HopApp::create($row + ['slug' => $slug, 'url' => rtrim($url, '/'), 'secret' => $secret, 'status' => HopApp::STATUS_ACTIVE]);
        }

        return self::SUCCESS;
    }
}
