<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Support\NavSections;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exceptions to somebody's role: give one person one section, usually for a
 * while, without inventing a role for them.
 *
 * The thing this replaces: `users.nav_permissions` replaced the role list
 * outright, so adding one section meant reproducing all thirty checkboxes and
 * then maintaining that copy by hand forever. Nobody did it. They made people
 * admins instead - 6 of 15 accounts on this system are Admin.
 */
class UserPermissionGrantController extends Controller
{
    private function authorizeManaging(Request $request): void
    {
        $actor = $request->user();

        if (! $actor->isRole('Admin') && ! $actor->isRole('System Admin')) {
            abort(403, 'Only administrators can change access.');
        }
    }

    /**
     * What this person can reach, and why.
     *
     * Returns the resolved answer per section alongside where it came from, so
     * the screen can say "from their role" or "given by you until Friday"
     * rather than showing a checkbox with no explanation.
     */
    public function index(Request $request, User $user)
    {
        $this->authorizeManaging($request);

        $user->loadMissing('role', 'permissionGrants.grantedBy', 'permissionGrants.revokedBy');

        $isFullAccess = Role::nameHasFullMenuAccess($user->role?->name);

        $sections = [];

        foreach (NavSections::labels() as $key => $label) {
            $override = $isFullAccess ? null : $user->navSectionOverride($key);

            $sections[] = [
                'key' => $key,
                'label' => $label,
                'allowed' => $key === 'pos_support' ? $user->canAccessPosSupport() : $user->allowsNavSection($key),
                'locked' => $key === 'dashboard' || $isFullAccess,
                'source' => match (true) {
                    $key === 'dashboard' => 'always',
                    $isFullAccess => 'full_access_role',
                    $override === true => 'granted',
                    $override === false => 'revoked',
                    default => 'role',
                },
            ];
        }

        return response()->json([
            'role' => $user->role?->name,
            'role_has_full_access' => $isFullAccess,
            'sections' => $sections,
            'grants' => $user->permissionGrants
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (UserPermissionGrant $g) => $this->present($g)),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $this->authorizeManaging($request);

        $data = $request->validate([
            'section' => ['required', 'string', Rule::in(NavSections::keys())],
            'effect' => ['required', Rule::in([UserPermissionGrant::EFFECT_GRANT, UserPermissionGrant::EFFECT_REVOKE])],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['section'] === 'dashboard') {
            return response()->json([
                'message' => 'Everyone needs the dashboard - it is where the app opens.',
                'errors' => ['section' => ['The dashboard cannot be changed.']],
            ], 422);
        }

        if (Role::nameHasFullMenuAccess($user->role?->name)) {
            return response()->json([
                'message' => 'This person is an administrator, so they already have every section. '
                    .'Change their role first if you want to limit them.',
                'errors' => ['section' => ['Administrators cannot be limited here.']],
            ], 422);
        }

        // Replacing rather than stacking: two live rows for the same section is
        // a question with two answers, and the screen would have no honest way
        // to show it.
        $user->permissionGrants()
            ->active()
            ->where('section', $data['section'])
            ->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id]);

        $grant = $user->permissionGrants()->create([
            'section' => $data['section'],
            'effect' => $data['effect'],
            'expires_at' => $data['expires_at'] ?? null,
            'reason' => $data['reason'] ?? null,
            'granted_by' => $request->user()->id,
        ]);

        return response()->json($this->present($grant->fresh(['grantedBy'])), 201);
    }

    /**
     * Switch one off early.
     *
     * The row is kept rather than deleted: who had what, and for how long, is
     * the whole point of recording it.
     */
    public function destroy(Request $request, User $user, UserPermissionGrant $grant)
    {
        $this->authorizeManaging($request);

        if ($grant->user_id !== $user->id) {
            abort(404);
        }

        if ($grant->revoked_at === null) {
            $grant->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id]);
        }

        return response()->json($this->present($grant->fresh(['grantedBy', 'revokedBy'])));
    }

    private function present(UserPermissionGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'section' => $grant->section,
            'section_label' => NavSections::labels()[$grant->section] ?? $grant->section,
            'effect' => $grant->effect,
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'reason' => $grant->reason,
            'granted_by' => $grant->grantedBy?->name,
            'revoked_at' => $grant->revoked_at?->toIso8601String(),
            'revoked_by' => $grant->revokedBy?->name,
            'active' => $grant->isActive(),
            'summary' => $grant->summary(),
        ];
    }
}
