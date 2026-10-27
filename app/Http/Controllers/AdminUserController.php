<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\User;
use App\Services\UserDeletion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function __construct(private readonly UserDeletion $deletion) {}

    public function index(Request $request)
    {
        $query = User::query()->withCount('links')->withSum('links', 'clicks');
        $search = $request->string('q')->trim()->toString();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $status = $request->string('status')->toString();
        if (in_array($status, [User::STATUS_ACTIVE, User::STATUS_SUSPENDED], true)) {
            $query->where('status', $status);
        }
        match ($request->string('sort')->toString()) {
            'links' => $query->orderByDesc('links_count'),
            'login' => $query->orderByRaw('last_login_at IS NULL')->orderByDesc('last_login_at'),
            'name' => $query->orderBy('name'),
            default => $query->latest('id'),
        };
        $users = $query->paginate(20)->withQueryString();

        return view('admin-users', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'sort' => $request->string('sort')->toString(),
            'counts' => [
                'all' => User::count(),
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'suspended' => User::where('status', User::STATUS_SUSPENDED)->count(),
                'new' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'viewerIsSuperAdmin' => $request->user()->isSuperAdmin(),
        ]);
    }

    /**
     * Owner picker for admin filters: the first matches by name or email.
     */
    public function lookup(Request $request)
    {
        $term = $request->string('q')->trim()->toString();
        $users = User::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    public function inspect(Request $request, User $user)
    {
        $user->loadCount(['links', 'folders', 'tags']);
        $links = $user->links()
            ->with(['folder:id,name', 'tags:id,name'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin-user-inspect', [
            'user' => $user,
            'links' => $links,
            'folders' => $user->folders()->withCount('links')->orderByDesc('is_default')->orderBy('name')->limit(50)->get(),
            'tags' => $user->tags()->withCount('links')->orderByDesc('links_count')->limit(50)->get(),
            'totalClicks' => (int) $user->links()->sum('clicks'),
            'activeLinks' => $user->links()->live()->count(),
            'bioPages' => $user->bioPages()->get(['id', 'slug', 'title', 'is_published']),
            'viewerIsSuperAdmin' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function storeAdmin(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        Folder::create([
            'name' => 'Default',
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        return back()->with('status', "{$user->name} was added as a user.");
    }

    public function updateStatus(Request $request, User $user)
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['status' => 'The superadmin account cannot be suspended.']);
        }
        $validated = $request->validate([
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
        ]);
        if ($user->id === $request->user()->id && $validated['status'] !== User::STATUS_ACTIVE) {
            return back()->withErrors(['status' => 'Anda tidak bisa mensuspend akun sendiri.']);
        }
        $user->update(['status' => $validated['status']]);

        return back()->with('status', "Status {$user->name} diperbarui.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'You cannot remove the account currently signed in.']);
        }
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['delete' => 'The superadmin account cannot be deleted.']);
        }
        $this->deletion->delete($user);

        return back()->with('status', "{$user->email} has been removed.");
    }
}
