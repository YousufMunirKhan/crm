<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Modules\HR\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role');

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

    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);
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
        ]);

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
        ]));
        
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
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json($user->load('role'));
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
