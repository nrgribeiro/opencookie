<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-ADMIN-4 — list, edit, and delete user accounts. The platform must
 * always retain at least one super admin.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $users = User::query()
            ->with('tier:id,name')
            ->withCount('domains')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'tier' => $u->tier?->name,
                'isSuperAdmin' => $u->hasRole(Role::SuperAdmin->value),
                'domains' => $u->domains_count,
                'createdAt' => $u->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tierId' => $user->tier_id,
                'isSuperAdmin' => $user->hasRole(Role::SuperAdmin->value),
                'domains' => $user->domains()->count(),
            ],
            'tiers' => Tier::orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update(['tier_id' => $request->validated('tier_id')]);

        $makeAdmin = $request->boolean('is_super_admin');
        $isAdmin = $user->hasRole(Role::SuperAdmin->value);

        if (! $makeAdmin && $isAdmin && $this->isLastSuperAdmin($user)) {
            return back()->withErrors([
                'is_super_admin' => 'Cannot revoke the last super admin. Promote another user first.',
            ]);
        }

        if ($makeAdmin && ! $isAdmin) {
            $user->assignRole(Role::SuperAdmin->value);
        } elseif (! $makeAdmin && $isAdmin) {
            $user->removeRole(Role::SuperAdmin->value);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole(Role::SuperAdmin->value) && $this->isLastSuperAdmin($user)) {
            return back()->withErrors(['user' => 'Cannot delete the last super admin.']);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted.');
    }

    /** True when $user is the only user holding the super_admin role. */
    private function isLastSuperAdmin(User $user): bool
    {
        return User::role(Role::SuperAdmin->value)->whereKeyNot($user->getKey())->doesntExist();
    }
}
