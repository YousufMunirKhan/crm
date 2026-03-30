<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\NavSections;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::query()->orderBy('name')->get());
    }

    public function update(Request $request, Role $role)
    {
        $user = $request->user();
        if (! $user->isRole('Admin') && ! $user->isRole('System Admin')) {
            abort(403, 'Only administrators can update role access.');
        }

        if ($role->hasFullMenuAccess()) {
            if ($role->nav_permissions !== null) {
                $role->nav_permissions = null;
                $role->save();
            }

            return response()->json($role->fresh());
        }

        $data = $request->validate([
            'nav_permissions' => ['sometimes', 'nullable', 'array'],
        ]);

        if (! array_key_exists('nav_permissions', $data)) {
            return response()->json($role->fresh());
        }

        $raw = $data['nav_permissions'];
        if ($raw === null) {
            $role->nav_permissions = null;
        } else {
            $sanitized = NavSections::sanitize($raw);
            $role->nav_permissions = $sanitized === [] ? null : $sanitized;
        }

        $role->save();

        return response()->json($role->fresh());
    }
}
