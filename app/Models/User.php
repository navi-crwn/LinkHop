<?php

namespace App\Models;

use Hop\Auth\Models\ApiToken;
use Hop\Bio\Models\BioPage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * The shared `users` table, owned by the hub (HOP ID). This app only reads it:
 * sign-in comes through an SSO ticket (Auth::loginUsingId), profile changes,
 * roles, suspension and deletion happen at the hub.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_USER = 'user';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'bio',
        'website',
        'location',
        'locale',
        'timezone',
        'show_owner',
        'role',
        'status',
        'last_login_ip',
        'last_login_country',
        'last_login_city',
        'last_login_provider',
        'last_login_device',
        'last_login_browser',
        'last_login_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'show_owner' => true,
        'profile_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
            'status' => 'string',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'show_owner' => 'boolean',
            'profile_version' => 'integer',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function bioPages(): HasMany
    {
        return $this->hasMany(BioPage::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Roles on HOP ID. Global roles (owner, admin, moderator, support) have no
     * app; "admin" scoped to an app makes someone admin of that app only.
     *
     * @return list<string> e.g. ["owner"], ["admin"], ["admin:pixelhop"]
     */
    public function roleNames(): array
    {
        $names = [];
        foreach ($this->roles as $r) {
            $names[] = $r->app_slug ? $r->role.':'.$r->app_slug : $r->role;
        }
        // Until hop:bootstrap runs, SUPERADMIN_EMAIL still names the owner, so a
        // fresh deploy never locks the owner out.
        $superadminEmail = config('auth.superadmin_email');
        if ($superadminEmail && strcasecmp($this->email, $superadminEmail) === 0 && ! in_array(Role::OWNER, $names, true)) {
            $names[] = Role::OWNER;
        }

        return array_values(array_unique($names));
    }

    public function hasRole(string $role, ?string $app = null): bool
    {
        return in_array($app ? $role.':'.$app : $role, $this->roleNames(), true);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Role::OWNER);
    }

    /** Admin of HOP (every app): owner or admin. */
    public function isAdmin(): bool
    {
        return $this->isOwner() || $this->hasRole(Role::ADMIN);
    }

    /** Admin of one app: global admin, or the app-scoped admin role. */
    public function isAdminOf(string $appSlug): bool
    {
        return $this->isAdmin() || $this->hasRole(Role::ADMIN, $appSlug);
    }

    /** Can moderate content: admin or moderator. */
    public function isModerator(): bool
    {
        return $this->isAdmin() || $this->hasRole(Role::MODERATOR);
    }

    /** @deprecated use isOwner(); kept while older code and tests say "superadmin". */
    public function isSuperAdmin(): bool
    {
        return $this->isOwner();
    }

    /** The picture to show: uploaded here first, else the Google/external one. */
    public function avatarUrl(): ?string
    {
        if ($this->avatar_path) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        return $this->avatar ?: null;
    }

    /** Verified at the hub (by email, or by Google which verifies the address itself). */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null || (bool) $this->google_id;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }
}
