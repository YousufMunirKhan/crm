<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Salary;
use App\Modules\HR\Models\EmployeeTarget;
use App\Modules\HR\Models\EmployeeDocument;
use App\Modules\HR\Services\HrService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class HrController extends Controller
{
    public function __construct(
        private HrService $hrService
    ) {}

    public function checkIn(Request $request)
    {
        $user = $request->user();
        
        // Only non-admin users can check in
        if ($user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin')) {
            return response()->json(['error' => 'Admin users cannot check in. Please use the attendance management section.'], 403);
        }

        $userId = $user->id;

        try {
            $attendance = $this->hrService->checkIn($userId);
            return response()->json($attendance, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        
        // Only non-admin users can check out
        if ($user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin')) {
            return response()->json(['error' => 'Admin users cannot check out. Please use the attendance management section.'], 403);
        }

        $userId = $user->id;

        try {
            $attendance = $this->hrService->checkOut($userId);
            return response()->json($attendance);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function attendance(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isRole('Admin') || $user->isRole('Manager');
        
        $query = Attendance::with('user');

        // Non-admin users can only see their own attendance
        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from_date')) {
            $query->where('date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }

        if ($request->has('month')) {
            $month = Carbon::parse($request->month . '-01');
            $query->whereYear('date', $month->year)
                  ->whereMonth('date', $month->month);
        }

        $attendance = $query->orderBy('date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($attendance);
    }

    public function todayStatus(Request $request)
    {
        $user = $request->user();
        
        // Admin users don't need today status
        if ($user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin')) {
            return response()->json([
                'checked_in' => false,
                'checked_out' => false,
                'check_in_time' => null,
                'check_out_time' => null,
                'attendance' => null,
                'is_admin' => true,
            ]);
        }

        $userId = $user->id;
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        return response()->json([
            'checked_in' => $attendance?->check_in_at !== null,
            'checked_out' => $attendance?->check_out_at !== null,
            'check_in_time' => $attendance?->check_in_at,
            'check_out_time' => $attendance?->check_out_at,
            'attendance' => $attendance,
            'server_date' => $today,
            'server_time' => now()->toDateTimeString(),
            'is_admin' => false,
        ]);
    }

    public function salaries(Request $request)
    {
        $query = Salary::with('user.role');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('from_month')) {
            $query->where('month', '>=', $request->from_month);
        }

        if ($request->has('to_month')) {
            $query->where('month', '<=', $request->to_month);
        }

        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }

        $salaries = $query->orderBy('month', 'desc')
            ->orderBy('user_id')
            ->paginate($request->get('per_page', 15));

        return response()->json($salaries);
    }

    public function employees(Request $request)
    {
        $query = \App\Models\User::with('role')->where('is_active', true);

        // Search by name or email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && $request->role) {
            $query->whereHas('role', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        $users = $query->orderBy('name')
            ->paginate($request->get('per_page', 12));

        return response()->json($users);
    }

    public function employeeTargets(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $month = $request->input('month') ?: now()->format('Y-m');

        $targets = EmployeeTarget::with('user.role')
            ->where('month', $month)
            ->get();

        return response()->json([
            'month' => $month,
            'data' => $targets,
        ]);
    }

    public function upsertEmployeeTarget(Request $request, $userId)
    {
        $data = $request->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'target_appointments' => ['nullable', 'integer', 'min:0'],
            'target_sales' => ['nullable', 'integer', 'min:0'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'meta' => ['nullable', 'array'],
        ]);

        $target = EmployeeTarget::updateOrCreate(
            ['user_id' => $userId, 'month' => $data['month']],
            [
                'target_appointments' => $data['target_appointments'] ?? 0,
                'target_sales' => $data['target_sales'] ?? 0,
                'target_revenue' => $data['target_revenue'] ?? 0,
                'meta' => $data['meta'] ?? null,
            ]
        );

        return response()->json($target->load('user.role'));
    }

    public function employeeAttendanceStats($userId)
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        // This month attendance days
        $thisMonth = Attendance::where('user_id', $userId)
            ->where('date', '>=', $startOfMonth->toDateString())
            ->whereNotNull('check_in_at')
            ->count();

        // This year attendance days
        $thisYear = Attendance::where('user_id', $userId)
            ->where('date', '>=', $startOfYear->toDateString())
            ->whereNotNull('check_in_at')
            ->count();

        // Total hours this month
        $totalHours = Attendance::where('user_id', $userId)
            ->where('date', '>=', $startOfMonth->toDateString())
            ->whereNotNull('check_out_at')
            ->sum('work_hours');

        return response()->json([
            'this_month' => $thisMonth,
            'this_year' => $thisYear,
            'total_hours' => round($totalHours, 2),
        ]);
    }

    public function employeeDocuments($userId)
    {
        $docs = EmployeeDocument::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($docs);
    }

    public function storeEmployeeDocument(Request $request, $userId)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $path = $request->file('file')->store('employee-documents', 'public');

        $doc = EmployeeDocument::create([
            'user_id' => $userId,
            'name' => $request->input('name'),
            'file_path' => '/storage/' . $path,
        ]);

        return response()->json($doc, 201);
    }

    public function destroyEmployeeDocument($userId, $id)
    {
        $doc = EmployeeDocument::where('user_id', $userId)->findOrFail($id);
        $doc->delete();

        return response()->noContent();
    }

    public function salaryReport(Request $request)
    {
        $user = $request->user();
        
        // Only admin can view reports
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Salary::with('user.role');

        // Filter by month range
        if ($request->has('from_month')) {
            $query->where('month', '>=', $request->from_month);
        }

        if ($request->has('to_month')) {
            $query->where('month', '<=', $request->to_month);
        }

        // Filter by specific month
        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by currency
        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }

        $salaries = $query->orderBy('month', 'desc')
            ->orderBy('user_id')
            ->get();

        // Calculate totals
        $totalGBP = $salaries->where('currency', 'GBP')->sum('net_salary');
        $totalPKR = $salaries->where('currency', 'PKR')->sum('net_salary');
        $totalSalaries = $salaries->count();
        $totalEmployees = $salaries->pluck('user_id')->unique()->count();

        // Group by month
        $monthlyData = $salaries->groupBy('month')->map(function ($monthSalaries) {
            return [
                'count' => $monthSalaries->count(),
                'total_gbp' => $monthSalaries->where('currency', 'GBP')->sum('net_salary'),
                'total_pkr' => $monthSalaries->where('currency', 'PKR')->sum('net_salary'),
                'salaries' => $monthSalaries->map(function ($salary) {
                    return [
                        'id' => $salary->id,
                        'user_id' => $salary->user_id,
                        'user_name' => $salary->user->name,
                        'user_role' => $salary->user->role->name ?? 'N/A',
                        'month' => $salary->month,
                        'base_salary' => $salary->base_salary,
                        'allowances' => $salary->allowances,
                        'net_salary' => $salary->net_salary,
                        'currency' => $salary->currency,
                    ];
                })->values(),
            ];
        });

        // Group by employee
        $employeeData = $salaries->groupBy('user_id')->map(function ($userSalaries, $userId) {
            $user = $userSalaries->first()->user;
            return [
                'user_id' => $userId,
                'user_name' => $user->name,
                'user_role' => $user->role->name ?? 'N/A',
                'total_salaries' => $userSalaries->count(),
                'total_gbp' => $userSalaries->where('currency', 'GBP')->sum('net_salary'),
                'total_pkr' => $userSalaries->where('currency', 'PKR')->sum('net_salary'),
                'salaries' => $userSalaries->map(function ($salary) {
                    return [
                        'id' => $salary->id,
                        'month' => $salary->month,
                        'base_salary' => $salary->base_salary,
                        'allowances' => $salary->allowances,
                        'net_salary' => $salary->net_salary,
                        'currency' => $salary->currency,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'summary' => [
                'total_salaries' => $totalSalaries,
                'total_employees' => $totalEmployees,
                'total_gbp' => $totalGBP,
                'total_pkr' => $totalPKR,
            ],
            'monthly' => $monthlyData,
            'by_employee' => $employeeData,
            'salaries' => $salaries->map(function ($salary) {
                return [
                    'id' => $salary->id,
                    'user_id' => $salary->user_id,
                    'user_name' => $salary->user->name,
                    'user_role' => $salary->user->role->name ?? 'N/A',
                    'month' => $salary->month,
                    'base_salary' => $salary->base_salary,
                    'allowances' => $salary->allowances,
                    'net_salary' => $salary->net_salary,
                    'currency' => $salary->currency,
                    'created_at' => $salary->created_at,
                ];
            }),
        ]);
    }

    public function exportSalaryReport(Request $request)
    {
        $user = $request->user();
        
        // Only admin can export reports
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Salary::with('user.role');

        // Apply same filters as report
        if ($request->has('from_month')) {
            $query->where('month', '>=', $request->from_month);
        }

        if ($request->has('to_month')) {
            $query->where('month', '<=', $request->to_month);
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('currency')) {
            $query->where('currency', $request->currency);
        }

        $salaries = $query->orderBy('month', 'desc')
            ->orderBy('user_id')
            ->get();

        $filename = 'salary_report_' . now()->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($salaries) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Payslip #',
                'Employee Name',
                'Designation',
                'Employee ID',
                'Month',
                'Base Salary',
                'Transport Allowance',
                'Net Salary',
                'Currency',
                'Date Created'
            ]);

            // Data rows
            foreach ($salaries as $salary) {
                fputcsv($file, [
                    'PS-' . str_pad($salary->id, 4, '0', STR_PAD_LEFT),
                    $salary->user->name,
                    $salary->user->role->name ?? 'N/A',
                    'EMP' . str_pad($salary->user->id, 3, '0', STR_PAD_LEFT),
                    \Carbon\Carbon::createFromFormat('Y-m', $salary->month)->format('F Y'),
                    number_format($salary->base_salary, 2),
                    number_format($salary->allowances ?? 0, 2),
                    number_format($salary->net_salary, 2),
                    $salary->currency,
                    $salary->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function createSalary(Request $request)
    {
        $user = $request->user();
        
        // Only admin can create salaries
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'house_allowance' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'loan_deduction' => ['nullable', 'numeric', 'min:0'],
            'other_deduction' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'in:GBP,PKR'],
            'bonuses' => ['nullable', 'array'],
            'bonuses.*.name' => ['required_with:bonuses', 'string'],
            'bonuses.*.amount' => ['required_with:bonuses', 'numeric', 'min:0'],
            'deductions_detail' => ['nullable', 'array'],
            'deductions_detail.*.name' => ['required_with:deductions_detail', 'string'],
            'deductions_detail.*.amount' => ['required_with:deductions_detail', 'numeric', 'min:0'],
            'attendance_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        // Calculate attendance days if not provided
        if (!isset($data['attendance_days'])) {
            $data['attendance_days'] = $this->calculateAttendanceDays($data['user_id'], $data['month']);
        }

        // Calculate total bonuses
        $totalBonuses = 0;
        if (isset($data['bonuses']) && is_array($data['bonuses'])) {
            foreach ($data['bonuses'] as $bonus) {
                $totalBonuses += $bonus['amount'] ?? 0;
            }
        }

        // Calculate total detailed deductions
        $totalDeductionsDetail = 0;
        if (isset($data['deductions_detail']) && is_array($data['deductions_detail'])) {
            foreach ($data['deductions_detail'] as $deduction) {
                $totalDeductionsDetail += $deduction['amount'] ?? 0;
            }
        }

        // Net salary = base + allowances + bonuses - deductions - deductions_detail
        $netSalary = $data['base_salary'] 
            + ($data['allowances'] ?? 0) 
            + $totalBonuses 
            - ($data['deductions'] ?? 0) 
            - $totalDeductionsDetail;

        $salary = Salary::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'month' => $data['month'],
            ],
            [
                'base_salary' => $data['base_salary'],
                'allowances' => $data['allowances'] ?? 0,
                'deductions' => $data['deductions'] ?? 0,
                'net_salary' => $netSalary,
                'currency' => $data['currency'],
                'bonuses' => $data['bonuses'] ?? null,
                'deductions_detail' => $data['deductions_detail'] ?? null,
                'attendance_days' => $data['attendance_days'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json($salary->load('user'), 201);
    }

    private function calculateAttendanceDays($userId, $month)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        return Attendance::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('check_in_at')
            ->count();
    }

    public function showSalary($id)
    {
        $salary = Salary::with('user')->findOrFail($id);
        return response()->json($salary);
    }

    public function updateSalary(Request $request, $id)
    {
        $user = $request->user();
        
        // Only admin can update salaries
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $salary = Salary::findOrFail($id);

        $data = $request->validate([
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'house_allowance' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'loan_deduction' => ['nullable', 'numeric', 'min:0'],
            'other_deduction' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'in:GBP,PKR'],
            'bonuses' => ['nullable', 'array'],
            'bonuses.*.name' => ['required_with:bonuses', 'string'],
            'bonuses.*.amount' => ['required_with:bonuses', 'numeric', 'min:0'],
            'deductions_detail' => ['nullable', 'array'],
            'deductions_detail.*.name' => ['required_with:deductions_detail', 'string'],
            'deductions_detail.*.amount' => ['required_with:deductions_detail', 'numeric', 'min:0'],
            'attendance_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        // Calculate total bonuses
        $totalBonuses = 0;
        if (isset($data['bonuses']) && is_array($data['bonuses'])) {
            foreach ($data['bonuses'] as $bonus) {
                $totalBonuses += $bonus['amount'] ?? 0;
            }
        } else {
            $totalBonuses = collect($salary->bonuses ?? [])->sum('amount');
        }

        // Calculate total detailed deductions
        $totalDeductionsDetail = 0;
        if (isset($data['deductions_detail']) && is_array($data['deductions_detail'])) {
            foreach ($data['deductions_detail'] as $deduction) {
                $totalDeductionsDetail += $deduction['amount'] ?? 0;
            }
        } else {
            $totalDeductionsDetail = collect($salary->deductions_detail ?? [])->sum('amount');
        }

        // Recalculate net salary
        $baseSalary = $data['base_salary'] ?? $salary->base_salary;
        $allowances = $data['allowances'] ?? $salary->allowances ?? 0;
        $houseAllowance = $data['house_allowance'] ?? $salary->house_allowance ?? 0;
        $medicalAllowance = $data['medical_allowance'] ?? $salary->medical_allowance ?? 0;
        $otherAllowance = $data['other_allowance'] ?? $salary->other_allowance ?? 0;
        $tax = $data['tax'] ?? $salary->tax ?? 0;
        $loanDeduction = $data['loan_deduction'] ?? $salary->loan_deduction ?? 0;
        $otherDeduction = $data['other_deduction'] ?? $salary->other_deduction ?? 0;
        $deductions = $data['deductions'] ?? $salary->deductions ?? 0;
        
        $totalEarnings = $baseSalary + $allowances + $houseAllowance + $medicalAllowance + $otherAllowance + $totalBonuses;
        $totalDeductions = $tax + $loanDeduction + $otherDeduction + $deductions + $totalDeductionsDetail;
        $data['net_salary'] = $totalEarnings - $totalDeductions;

        $salary->update($data);

        return response()->json($salary->load('user'));
    }

    public function deleteSalary($id)
    {
        $user = request()->user();
        
        // Only admin can delete salaries
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $salary = Salary::findOrFail($id);
        $salary->delete();

        return response()->noContent();
    }

    public function generateSalarySlip($id)
    {
        $salary = Salary::with('user')->findOrFail($id);
        
        // Calculate attendance days if not set
        if (!$salary->attendance_days) {
            $salary->attendance_days = $this->calculateAttendanceDays($salary->user_id, $salary->month);
            $salary->save();
        }

        // Get logo from settings
        $logoUrl = \App\Modules\Settings\Models\Setting::where('key', 'logo_url')->first()?->value;
        
        $pdf = Pdf::loadView('salaries.slip', [
            'salary' => $salary,
            'logoUrl' => $logoUrl,
        ])->setPaper('a4', 'portrait')
          ->setOption('margin-top', 10)
          ->setOption('margin-bottom', 10)
          ->setOption('margin-left', 10)
          ->setOption('margin-right', 10)
          ->setOption('enable-local-file-access', true)
          ->setOption('defaultFont', 'DejaVu Sans')
          ->setOption('isHtml5ParserEnabled', true)
          ->setOption('isRemoteEnabled', false);

        return $pdf->download("salary_slip_{$salary->user->name}_{$salary->month}.pdf");
    }

    public function sendSalarySlipEmail(Request $request, $id)
    {
        $salary = Salary::with('user')->findOrFail($id);

        // Calculate attendance days if not set
        if (!$salary->attendance_days) {
            $salary->attendance_days = $this->calculateAttendanceDays($salary->user_id, $salary->month);
            $salary->save();
        }

        $month = Carbon::createFromFormat('Y-m', $salary->month)->format('F Y');
        $companyName = \App\Modules\Settings\Models\Setting::where('key', 'company_name')->first()?->value ?? config('app.name', 'Company');
        $logoSetting = \App\Modules\Settings\Models\Setting::where('key', 'logo_url')->first()?->value ?? '';
        $logoUrl = '';
        $logoPath = null;
        if ($logoSetting) {
            $logoUrl = str_starts_with($logoSetting, 'http')
                ? $logoSetting
                : rtrim(config('app.url'), '/') . '/' . ltrim($logoSetting, '/');
            $cleanUrl = preg_replace('#^/storage/|^storage/#', '', trim($logoSetting, '/'));
            if ($cleanUrl) {
                $sp = storage_path('app/public/' . $cleanUrl);
                $pp = public_path('storage/' . $cleanUrl);
                $logoPath = file_exists($sp) ? $sp : (file_exists($pp) ? $pp : null);
            }
        }

        $employeeName = $salary->user->name ?? 'Employee';
        $socialFacebook = \App\Modules\Settings\Models\Setting::where('key', 'social_facebook_url')->first()?->value ?? '';
        $socialTwitter = \App\Modules\Settings\Models\Setting::where('key', 'social_twitter_url')->first()?->value ?? '';
        $socialLinkedIn = \App\Modules\Settings\Models\Setting::where('key', 'social_linkedin_url')->first()?->value ?? '';
        $socialInstagram = \App\Modules\Settings\Models\Setting::where('key', 'social_instagram_url')->first()?->value ?? '';
        $socialTikTok = \App\Modules\Settings\Models\Setting::where('key', 'social_tiktok_url')->first()?->value ?? '';
        $companyWebsite = \App\Modules\Settings\Models\Setting::where('key', 'company_website')->first()?->value ?? '';
        $companyPhone = \App\Modules\Settings\Models\Setting::where('key', 'company_phone')->first()?->value ?? '';
        $companyAddress = \App\Modules\Settings\Models\Setting::where('key', 'company_address')->first()?->value ?? '';

        try {
            \App\Services\MailConfigFromDatabase::apply();
            Mail::to($salary->user->email, $salary->user->name)->send(new \App\Mail\SalarySlipEmail(
                $salary,
                $month,
                $companyName,
                $logoUrl,
                $logoPath,
                $employeeName,
                $socialFacebook,
                $socialTwitter,
                $socialLinkedIn,
                $socialInstagram,
                $socialTikTok,
                $companyWebsite,
                $companyPhone,
                $companyAddress
            ));

            return response()->json(['message' => 'Salary slip sent successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    public function deleteAttendance($id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return response()->noContent();
    }
}


