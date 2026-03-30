<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Expense;
use App\Modules\HR\Models\ExpenseAttachment;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Only admin can access expenses
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Expense::with(['creator', 'attachments'])->orderBy('date', 'desc');

        if ($request->filled('status') && in_array($request->status, ['open', 'closed'], true)) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Filter by month for monthly report
        if ($request->has('month')) {
            $query->whereYear('date', substr($request->month, 0, 4))
                  ->whereMonth('date', substr($request->month, 5, 2));
        }

        $expenses = $query->paginate($request->get('per_page', 10));

        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        // Only admin can create expenses
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'in:GBP,PKR'],
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'in:open,closed'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $files = $request->file('attachments', []) ?: [];

        $data['created_by'] = $user->id;
        $data['status'] = $data['status'] ?? 'open';
        unset($data['attachments']);

        $expense = Expense::create($data);
        $this->saveUploadedAttachments($expense, is_array($files) ? $files : array_filter([$files]));

        return response()->json($expense->load(['creator', 'attachments']), 201);
    }

    public function show($id)
    {
        if (!$id || $id === 'undefined' || $id === 'null') {
            return response()->json(['error' => 'Invalid expense ID'], 400);
        }
        
        $expense = Expense::with(['creator', 'attachments'])->findOrFail($id);
        return response()->json($expense);
    }

    public function update(Request $request, $id)
    {
        if (!$id || $id === 'undefined' || $id === 'null') {
            return response()->json(['error' => 'Invalid expense ID'], 400);
        }
        
        $user = $request->user();
        
        // Only admin can update expenses
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $expense = Expense::findOrFail($id);

        $data = $request->validate([
            'date' => ['sometimes', 'required', 'date'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'in:GBP,PKR'],
            'reason' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'string', 'in:open,closed'],
        ]);

        $expense->update($data);

        return response()->json($expense->load(['creator', 'attachments']));
    }

    public function destroy($id)
    {
        if (!$id || $id === 'undefined' || $id === 'null') {
            return response()->json(['error' => 'Invalid expense ID'], 400);
        }
        
        $user = request()->user();
        
        // Only admin can delete expenses
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $expense = Expense::findOrFail($id);
        $expense->attachments->each->delete();
        $expense->delete();

        return response()->noContent();
    }

    public function bulkClose(Request $request)
    {
        $user = $request->user();

        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:expenses,id'],
        ]);

        $updated = Expense::whereIn('id', $data['ids'])
            ->where('status', 'open')
            ->update(['status' => 'closed']);

        return response()->json([
            'updated' => $updated,
            'message' => $updated > 0 ? "{$updated} expense(s) marked as closed." : 'No open expenses were updated.',
        ]);
    }

    public function storeAttachments(Request $request, $id)
    {
        if (!$id || $id === 'undefined' || $id === 'null') {
            return response()->json(['error' => 'Invalid expense ID'], 400);
        }

        $user = $request->user();

        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $expense = Expense::findOrFail($id);

        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:20'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $files = $request->file('attachments', []) ?: [];
        $this->saveUploadedAttachments($expense, is_array($files) ? $files : array_filter([$files]));

        return response()->json($expense->load(['creator', 'attachments']));
    }

    public function destroyAttachment(Request $request, $id, $attachmentId)
    {
        if (!$id || $id === 'undefined' || $id === 'null') {
            return response()->json(['error' => 'Invalid expense ID'], 400);
        }

        $user = $request->user();

        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $attachment = ExpenseAttachment::where('expense_id', $id)
            ->where('id', $attachmentId)
            ->firstOrFail();

        $attachment->delete();

        return response()->noContent();
    }

    public function monthlyReport(Request $request)
    {
        $user = $request->user();
        
        // Only admin can access reports
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $month = $request->get('month', now()->format('Y-m'));

        $expenses = Expense::whereYear('date', substr($month, 0, 4))
            ->whereMonth('date', substr($month, 5, 2))
            ->get();

        // Group by currency
        $byCurrency = $expenses->groupBy('currency')->map(function ($items) {
            return [
                'count' => $items->count(),
                'total' => $items->sum('amount'),
            ];
        });

        // Group by category with currency
        $byCategory = $expenses->groupBy(['category', 'currency'])->map(function ($categoryItems) {
            return $categoryItems->map(function ($items) {
                return [
                    'count' => $items->count(),
                    'total' => $items->sum('amount'),
                    'currency' => $items->first()->currency,
                ];
            });
        });

        return response()->json([
            'month' => $month,
            'total_count' => $expenses->count(),
            'by_currency' => $byCurrency,
            'by_category' => $byCategory,
            'expenses' => $expenses->load('creator'),
        ]);
    }

    public function import(Request $request)
    {
        $user = $request->user();
        
        // Only admin can import expenses
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Read CSV file
        if (($handle = fopen($path, 'r')) !== false) {
            // Skip header row
            $header = fgetcsv($handle);
            
            // Expected headers: Date, Amount, Currency, Reason, Description, Category
            $expectedHeaders = ['date', 'amount', 'currency', 'reason', 'description', 'category'];
            
            while (($row = fgetcsv($handle)) !== false) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                try {
                    // Map CSV columns to data array
                    $data = [
                        'date' => trim($row[0] ?? ''),
                        'amount' => trim($row[1] ?? ''),
                        'currency' => strtoupper(trim($row[2] ?? '')),
                        'reason' => trim($row[3] ?? ''),
                        'description' => trim($row[4] ?? ''),
                        'category' => trim($row[5] ?? ''),
                    ];

                    // Validate required fields
                    if (empty($data['date']) || empty($data['amount']) || empty($data['currency']) || empty($data['reason']) || empty($data['category'])) {
                        $skipped++;
                        $errors[] = [
                            'row' => $imported + $skipped + 1,
                            'error' => 'Missing required fields (Date, Amount, Currency, Reason, Category)',
                        ];
                        continue;
                    }

                    // Validate currency
                    if (!in_array($data['currency'], ['GBP', 'PKR'])) {
                        $skipped++;
                        $errors[] = [
                            'row' => $imported + $skipped + 1,
                            'error' => 'Invalid currency. Must be GBP or PKR',
                        ];
                        continue;
                    }

                    // Validate amount
                    if (!is_numeric($data['amount']) || $data['amount'] < 0) {
                        $skipped++;
                        $errors[] = [
                            'row' => $imported + $skipped + 1,
                            'error' => 'Invalid amount. Must be a positive number',
                        ];
                        continue;
                    }

                    // Validate date - try multiple formats
                    try {
                        $date = \Carbon\Carbon::parse($data['date']);
                        $data['date'] = $date->format('Y-m-d');
                    } catch (\Exception $e) {
                        $skipped++;
                        $errors[] = [
                            'row' => $imported + $skipped + 1,
                            'error' => 'Invalid date format. Use YYYY-MM-DD',
                        ];
                        continue;
                    }

                    // Create expense
                    Expense::create([
                        'date' => $data['date'],
                        'amount' => $data['amount'],
                        'currency' => $data['currency'],
                        'reason' => $data['reason'],
                        'description' => $data['description'] ?: null,
                        'category' => $data['category'],
                        'created_by' => $user->id,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = [
                        'row' => $imported + $skipped + 1,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            fclose($handle);
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Successfully imported {$imported} expense(s). " . ($skipped > 0 ? "{$skipped} row(s) skipped." : ''),
        ]);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="expenses_template.csv"',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8 to help Excel recognize the encoding
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header row
            fputcsv($file, ['Date', 'Amount', 'Currency', 'Reason', 'Description', 'Category']);
            
            // Sample data rows
            $samples = [
                ['2026-02-01', '150.00', 'GBP', 'Office Supplies', 'Printer paper and ink cartridges', 'Office Supplies'],
                ['2026-02-05', '2500.00', 'PKR', 'Internet Bill', 'Monthly internet subscription', 'Utilities'],
                ['2026-02-10', '75.50', 'GBP', 'Team Lunch', 'Team meeting lunch at restaurant', 'Meals & Entertainment'],
                ['2026-02-15', '500.00', 'PKR', 'Transportation', 'Fuel and parking expenses', 'Travel'],
                ['2026-02-20', '1200.00', 'GBP', 'Software License', 'Annual software subscription renewal', 'Software & Tools'],
                ['2026-02-25', '350.00', 'PKR', 'Marketing Materials', 'Brochures and flyers printing', 'Marketing'],
            ];
            
            foreach ($samples as $sample) {
                fputcsv($file, $sample);
            }
            
            fclose($file);
        };

        return response()->streamDownload($callback, 'expenses_template.csv', $headers);
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function saveUploadedAttachments(Expense $expense, array $files): void
    {
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $path = $file->store('expense-attachments', 'public');

            $expense->attachments()->create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}


