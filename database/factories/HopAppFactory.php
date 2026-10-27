<?php

namespace Database\Factories;

use App\Models\HopApp;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HopApp> */
class HopAppFactory extends Factory
{
    protected $model = HopApp::class;

    public function definition(): array
    {
        return [
            'slug' => 'app'.fake()->unique()->numberBetween(1, 9999),
            'name' => 'Test App',
            'url' => 'https://app.example.com',
            'callback_path' => '/auth/sso/callback',
            'start_path' => '/auth/sso',
            'pages' => ['default' => '/dashboard'],
            'continue_page' => 'default',
            'secret' => str_repeat('s', 40),
            'capabilities' => ['account_api'],
            'status' => HopApp::STATUS_ACTIVE,
        ];
    }

    public function pixelhop(string $secret = 'test-secret-that-is-long-enough-1234567890'): static
    {
        return $this->state(['slug' => 'pixelhop', 'name' => 'PixelHop', 'menu_label' => 'Images', 'icon' => 'image',
            'url' => 'https://p.example.com', 'callback_path' => '/auth/helink/callback', 'start_path' => '/auth/helink',
            'pages' => ['default' => '/dashboard', 'upload' => '/'], 'continue_page' => 'upload', 'secret' => $secret, 'sort_order' => 10]);
    }

    public function notehop(string $secret = 'notehop-secret-that-is-long-enough-12345'): static
    {
        return $this->state(['slug' => 'notehop', 'name' => 'NoteHop', 'menu_label' => 'Notes', 'icon' => 'note',
            'url' => 'https://note.example.com', 'callback_path' => '/auth/sso/callback', 'start_path' => '/auth/sso',
            'pages' => ['default' => '/dashboard', 'new' => '/'], 'continue_page' => 'default', 'secret' => $secret, 'sort_order' => 20]);
    }

    /** The control center: hidden from menus, allowed to use the admin API. */
    public function imperium(string $secret = 'imperium-secret-that-is-long-enough-1234'): static
    {
        return $this->state(['slug' => 'imperium', 'name' => 'Imperium', 'menu_label' => null, 'url' => 'https://imp.example.com',
            'pages' => ['default' => '/'], 'secret' => $secret, 'capabilities' => ['admin_client'], 'sort_order' => 1, 'hidden' => true]);
    }
}
