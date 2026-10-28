<?php

namespace App\Models;

use App\Jobs\SendHopEvent;
use Hop\Bio\Models\BioPage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
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

    /** Columns every HOP app keeps a copy of. */
    public const PROFILE_FIELDS = ['username', 'name', 'email', 'avatar', 'avatar_path', 'bio', 'website', 'location', 'locale', 'show_owner'];

    protected static function booted(): void
    {
        // Profile changes bump profile_version and reach the apps; status changes
        // and deletions too. saveQuietly() (last_login_*) skips all of this.
        static::updating(function (User $user) {
            if ($user->isDirty(self::PROFILE_FIELDS)) {
                $user->profile_version = max(1, (int) $user->profile_version) + 1;
            }
        });
        static::updated(function (User $user) {
            if ($user->wasChanged(self::PROFILE_FIELDS)) {
                SendHopEvent::broadcast('profile.updated', $user->hopProfile());
            }
            if ($user->wasChanged('status')) {
                SendHopEvent::broadcast('account.status', ['sub' => (string) $user->id, 'status' => $user->status]);
            }
        });
        static::deleted(function (User $user) {
            SendHopEvent::broadcast('account.deleted', ['sub' => (string) $user->id, 'email' => $user->email]);
        });
    }

    /** Takes a new username, keeping the old one on hold for other people. */
    public function changeUsername(string $username): void
    {
        if ($this->username !== null && $this->username !== $username) {
            UsernameHistory::create(['user_id' => $this->id, 'username' => $this->username, 'released_at' => now()]);
        }
        $this->username = $username;
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

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
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

    /**
     * The profile every HOP app gets (in the SSO ticket and from the profile API).
     *
     * @return array<string, mixed>
     */
    public function hopProfile(): array
    {
        return [
            'sub' => (string) $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatarUrl() ?? '',
            'bio' => (string) $this->bio,
            'website' => (string) $this->website,
            'location' => (string) $this->location,
            'locale' => (string) $this->locale,
            'show_owner' => (bool) $this->show_owner,
            'roles' => $this->roleNames(),
            'status' => $this->status,
            'verified' => $this->hasVerifiedEmail() || (bool) $this->google_id,
            'mfa' => (bool) ($this->two_factor_secret && $this->two_factor_confirmed_at),
            'profile_version' => max(1, (int) $this->profile_version),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }
}
