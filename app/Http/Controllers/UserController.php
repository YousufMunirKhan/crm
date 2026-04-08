<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Support\NavSections;
use App\Modules\HR\Services\ContractService;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role');

        $viewer = $request->user();
        if ($request->boolean('for_sales_report') && $viewer) {
            $elevated = $viewer->isRole('Admin')
                || $viewer->isRole('Manager')
                || $viewer->isRole('System Admin');
            if (! $elevated) {
                $ids = app(ReportingService::class)->userIdsWithRecordedSales();
                $ids[] = (int) $viewer->id;
                $ids = array_values(array_unique(array_filter($ids)));
                $query->whereIn('id', $ids);
            }
        }

        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // By default only show active employees; allow overriding with is_active=0/1/all
        $isActive = $request->get('is_active', '1');
        if ($isActive === '0' || $isActive === 0) {
            $query->where('is_active', false);
        } elseif ($isActive !== 'all') {
            $query->where('is_active', true);
        }

        if ($request->has('employee_type')) {
            $query->where('employee_type', $request->employee_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Support pagination
        if ($request->has('per_page') || $request->has('page')) {
            $perPage = $request->get('per_page', 15);
            $users = $query->orderBy('name')->paginate($perPage);
            return response()->json($users);
        }

        $users = $query->orderBy('name')->get();
        return response()->json($users);
    }

    public function show(Request $request, $id)
    {
        $user = User::with('role')->findOrFail($id);
        if (! $this->canViewUserProfile($request->user(), $user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($user);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'employee_type' => ['nullable', 'in:field_worker,call_center,ticket_manager'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'hire_date' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_sort_code' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'send_contract' => ['nullable', 'boolean'],
            'contract_data' => ['nullable', 'array'],
            'nav_permissions' => ['nullable', 'array'],
        ]);

        $canSetNav = $request->user()->isRole('Admin') || $request->user()->isRole('System Admin');
        if (! $canSetNav) {
            unset($data['nav_permissions']);
        } elseif (array_key_exists('nav_permissions', $data)) {
            $data['nav_permissions'] = $this->normalizeNavPermissions($data['nav_permissions'] ?? null);
        }

        $data['password'] = Hash::make($data['password']);
        $sendContract = $request->boolean('send_contract', false);
        
        // Remove non-database fields
        $userData = array_intersect_key($data, array_flip([
            'name',
            'email',
            'password',
            'role_id',
            'employee_type',
            'phone',
            'address',
            'hire_date',
            'date_of_birth',
            'bank_account_name',
            'bank_name',
            'bank_sort_code',
            'bank_account_number',
            'is_active',
            'nav_permissions',
        ]));

        if ($this->roleIdHasFullMenuAccess((int) $userData['role_id'])) {
            $userData['nav_permissions'] = null;
        }

        $user = User::create($userData);

        // Generate and send contract if requested
        if ($sendContract) {
            try {
                $contractService = app(ContractService::class);
                $contractData = $request->input('contract_data', []);
                $contractService->generateAndSend($user, $contractData);
            } catch (\Exception $e) {
                // Log error but don't fail user creation
                \Log::error('Failed to send contract for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        return response()->json($user->load('role'), 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $viewer = $request->user();
        $isElevated = $viewer->isRole('Admin') || $viewer->isRole('Manager') || $viewer->isRole('System Admin');
        $isSelf = (int) $viewer->id === (int) $user->id;

        if (! $isElevated && ! $isSelf) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Non-admin users may only update their own bank details (HR / payroll).
        if ($isSelf && ! $isElevated) {
            $data = $request->validate([
                'bank_account_name' => ['nullable', 'string', 'max:255'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'bank_sort_code' => ['nullable', 'string', 'max:32'],
                'bank_account_number' => ['nullable', 'string', 'max:64'],
            ]);
            $user->update($data);

            return response()->json($user->load('role'));
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'employee_type' => ['nullable', 'in:field_worker,call_center,ticket_manager'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'hire_date' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_sort_code' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'nav_permissions' => ['nullable', 'array'],
        ]);

        $canSetNav = $viewer->isRole('Admin') || $viewer->isRole('System Admin');
        if ($canSetNav && array_key_exists('nav_permissions', $data)) {
            $data['nav_permissions'] = $this->normalizeNavPermissions($data['nav_permissions'] ?? null);
        } else {
            unset($data['nav_permissions']);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $effectiveRoleId = isset($data['role_id']) ? (int) $data['role_id'] : (int) $user->role_id;
        if ($this->roleIdHasFullMenuAccess($effectiveRoleId)) {
            $data['nav_permissions'] = null;
        }

        $user->update($data);

        return response()->json($user->load('role'));
    }

    protected function canViewUserProfile(User $viewer, User $target): bool
    {
        if ((int) $viewer->id === (int) $target->id) {
            return true;
        }

        return $viewer->isRole('Admin') || $viewer->isRole('Manager') || $viewer->isRole('System Admin');
    }

    protected function roleIdHasFullMenuAccess(int $roleId): bool
    {
        $role = Role::query()->whereKey($roleId)->first();

        return $role !== null && $role->hasFullMenuAccess();
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, bool>|null
     */
    protected function normalizeNavPermissions(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $sanitized = NavSections::sanitize($raw);

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * Set the same new password for every user except the caller (so you stay logged in).
     * Admin / System Admin only. Revokes Sanctum API tokens for affected users.
     */
    public function resetAllPasswords(Request $request)
    {
        $viewer = $request->user();
        if (! $viewer->isRole('Admin') && ! $viewer->isRole('System Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'confirm_phrase' => ['required', 'string', 'in:RESET ALL'],
            'skip_protected_accounts' => ['nullable', 'boolean'],
        ]);

        $hash = Hash::make($data['password']);
        $skipProtected = $request->boolean('skip_protected_accounts', true);

        $query = User::query()->where('id', '!=', $viewer->id);
        if ($skipProtected) {
            $query->where('email', '!=', 'admin@switchsave.com');
        }

        $ids = $query->pluck('id')->all();
        if ($ids === []) {
            return response()->json([
                'message' => 'No accounts matched. Your own user is never changed.',
                'affected' => 0,
            ]);
        }

        User::whereIn('id', $ids)->update(['password' => $hash]);

        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $ids)
            ->delete();

        Log::warning('Bulk password reset executed', [
            'by_user_id' => $viewer->id,
            'by_email' => $viewer->email,
            'affected_count' => count($ids),
            'skip_protected_accounts' => $skipProtected,
            'ip' => $request->ip(),
        ]);

        $n = count($ids);

        return response()->json([
            'message' => "Passwords updated for {$n} user(s). They must sign in again on other devices.",
            'affected' => $n,
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting the admin user
        if ($user->email === 'admin@switchsave.com') {
            return response()->json(['message' => 'Cannot delete admin user'], 403);
        }

        $user->delete();

        return response()->noContent();
    }
}
