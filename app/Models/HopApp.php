<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An app in the HOP family that signs people in with HOP ID (PixelHop,
 * NoteHop, …). One row replaces the old per-app config keys; adding an app
 * is adding a row, no code.
 */
class HopApp extends Model
{
    use HasFactory;

    protected $table = 'apps';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_OFF = 'off';

    protected $fillable = [
        'slug', 'name', 'url', 'callback_path', 'start_path', 'pages', 'continue_page', 'menu_label',
        'icon', 'color', 'status', 'secret', 'secret_prev', 'secret_prev_until', 'capabilities', 'sort_order', 'hidden',
    ];

    protected $hidden = ['secret', 'secret_prev'];

    protected function casts(): array
    {
        return [
            'pages' => 'array',
            'capabilities' => 'array',
            'secret' => 'encrypted',
            'secret_prev' => 'encrypted',
            'secret_prev_until' => 'datetime',
            // The `hidden` column (not Eloquent's $hidden): registered but kept out of menus.
            'hidden' => 'boolean',
        ];
    }

    /** Apps for menus: not switched off and not hidden (Imperium is registered but hidden). */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_OFF)->where('hidden', false)->orderBy('sort_order')->orderBy('name');
    }

    /** Every app that is not switched off, hidden ones included (SSO, CSP origins). */
    public function scopeRegistered(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_OFF)->orderBy('sort_order')->orderBy('name');
    }

    /** The app with this slug, if it is configured and not switched off (hidden apps included). */
    public static function find_(string $slug): ?self
    {
        return self::query()->where('slug', $slug)->where('status', '!=', self::STATUS_OFF)->first();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Origin (scheme://host[:port]) for the CSP form-action list. */
    public function origin(): ?string
    {
        $p = parse_url($this->url);
        if (! $p || ! isset($p['scheme'], $p['host'])) {
            return null;
        }

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
    }

    public function baseUrl(): string
    {
        return rtrim($this->url, '/');
    }

    public function callbackUrl(): string
    {
        return $this->baseUrl().$this->callback_path;
    }

    /** The page "open" lands on: a key of `pages`, or the default. */
    public function page(?string $key): string
    {
        $pages = $this->pages ?: ['default' => '/'];

        return $pages[(string) $key] ?? $pages['default'] ?? '/';
    }

    public function can(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    /** Secrets that currently verify requests from (and sign messages to) this app. */
    public function secrets(): array
    {
        $out = [];
        if (strlen((string) $this->secret) >= 32) {
            $out[] = $this->secret;
        }
        if ($this->secret_prev && $this->secret_prev_until && $this->secret_prev_until->isFuture() && strlen($this->secret_prev) >= 32) {
            $out[] = $this->secret_prev;
        }

        return $out;
    }

    public function isConfigured(): bool
    {
        return $this->secrets() !== [] && (str_starts_with($this->url, 'https://') || str_starts_with($this->url, 'http://'));
    }

    public function label(): string
    {
        return $this->menu_label ?: $this->name;
    }
}
