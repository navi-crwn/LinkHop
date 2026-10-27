<?php

namespace App\Console\Commands;

use App\Models\HopApp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-time move from the old settings to HOP ID: the SUPERADMIN_EMAIL account
 * becomes `owner`, and the PIXELHOP_* / NOTEHOP_* keys become rows in `apps`.
 * Safe to run again: it only adds what is missing.
 */
class HopBootstrap extends Command
{
    protected $signature = 'hop:bootstrap {--dry-run : Show what would change}';

    protected $description = 'Create the owner role and the app registry from the old .env settings';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $email = (string) config('auth.superadmin_email');
        if ($email !== '') {
            $owner = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            if (! $owner) {
                $this->warn("SUPERADMIN_EMAIL {$email}: no such user yet; the owner role is granted on first sign-in instead.");
            } elseif ($owner->roles()->where('role', Role::OWNER)->whereNull('app_slug')->exists()) {
                $this->line("owner: {$owner->email} already has the role.");
            } else {
                $this->info("owner: granting to {$owner->email}");
                $dry || $owner->roles()->create(['role' => Role::OWNER]);
            }
        }

        foreach ([
            'pixelhop' => ['name' => 'PixelHop', 'menu_label' => 'Images', 'icon' => 'image', 'callback_path' => '/auth/helink/callback',
                'start_path' => '/auth/helink', 'pages' => ['default' => '/dashboard', 'upload' => '/'], 'continue_page' => 'upload',
                'capabilities' => ['account_api'], 'sort_order' => 10],
            'notehop' => ['name' => 'NoteHop', 'menu_label' => 'Notes', 'icon' => 'note', 'callback_path' => '/auth/sso/callback',
                'start_path' => '/auth/sso', 'pages' => ['default' => '/dashboard', 'new' => '/'], 'continue_page' => 'default',
                'capabilities' => [], 'sort_order' => 20],
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
            $dry || HopApp::create($row + ['slug' => $slug, 'url' => rtrim($url, '/'), 'secret' => $secret]);
        }

        $missing = User::query()->whereNull('username')->count();
        if ($missing > 0) {
            $this->line("{$missing} users have no username yet: run hop:usernames to assign them.");
        }

        return self::SUCCESS;
    }
}
