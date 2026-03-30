<?php

namespace App\Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\CRM\Models\Customer;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');

        $query = Invoice::with(['customer', 'items', 'creator']);

        // For sales agents: show invoices they created OR for their assigned customers
        if ($isSalesAgent) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('customer', function ($subQ) use ($user) {
                        $subQ->whereHas('assignedUsers', function ($sq) use ($user) {
                            $sq->where('user_id', $user->id);
                        })->orWhereHas('leads', function ($sq) use ($user) {
                            $sq->where('assigned_to', $user->id);
                        });
                    });
            });
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->has('from_date')) {
            $query->where('invoice_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('invoice_date', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($subQuery) use ($search) {
                      $subQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('invoice_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($invoices);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['customer', 'items', 'creator'])->findOrFail($id);
        return response()->json($invoice);
    }

    public function store(Request $request)
    {
        $customerId = $request->input('customer_id');
        $newCustomer = $request->input('customer');

        if ($newCustomer && is_array($newCustomer)) {
            $validated = $request->validate([
                'customer' => ['required', 'array'],
                'customer.name' => ['required', 'string'],
                'customer.phone' => ['required', 'string'],
                'customer.email' => ['nullable', 'email'],
                'customer.address' => ['nullable', 'string'],
                'customer.vat_number' => ['nullable', 'string'],
                'invoice_date' => ['nullable', 'date'],
                'due_date' => ['nullable', 'date'],
                'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'status' => ['nullable', 'in:draft,sent,partially_paid,paid,overdue'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.description' => ['required', 'string'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            ]);
            $customer = Customer::create([
                'type' => Customer::TYPE_CUSTOMER,
                'name' => $newCustomer['name'],
                'phone' => $newCustomer['phone'],
                'email' => $newCustomer['email'] ?? null,
                'address' => $newCustomer['address'] ?? null,
                'vat_number' => $newCustomer['vat_number'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $customerId = $customer->id;
        } else {
            $request->validate([
                'customer_id' => ['required', 'exists:customers,id'],
                'invoice_date' => ['nullable', 'date'],
                'due_date' => ['nullable', 'date'],
                'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'status' => ['nullable', 'in:draft,sent,partially_paid,paid,overdue'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.description' => ['required', 'string'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            ]);
            $customerId = $request->input('customer_id');
        }

        $data = $request->only(['invoice_date', 'due_date', 'vat_rate', 'status', 'items']);
        $data['customer_id'] = $customerId;

        $invoice = $this->invoiceService->create($data, auth()->id());

        return response()->json($invoice->load(['customer', 'items']), 201);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $data = $request->validate([
            'customer_id' => ['sometimes', 'exists:customers,id'],
            'invoice_date' => ['sometimes', 'date'],
            'due_date' => ['nullable', 'date'],
            'vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'in:draft,sent,partially_paid,paid,overdue'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        if (isset($data['amount_paid'])) {
            $total = $data['items'] ? null : $invoice->total;
            if ($total === null && !empty($data['items'])) {
                $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
                $total = $subtotal + round($subtotal * ($data['vat_rate'] ?? $invoice->vat_rate) / 100, 2);
            }
            $total = $total ?? $invoice->total;
            if ($data['amount_paid'] >= $total) {
                $data['status'] = 'paid';
            } elseif ($data['amount_paid'] > 0) {
                $data['status'] = 'partially_paid';
            } else {
                $data['status'] = 'sent';
            }
        }

        $invoice = $this->invoiceService->update($invoice, $data);

        return response()->json($invoice);
    }

    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return response()->noContent();
    }

    public function generatePDF($id)
    {
        $invoice = Invoice::with(['customer', 'items'])->findOrFail($id);
        $pdf = $this->invoiceService->generatePDF($invoice);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    public function sendEmail(Request $request, $id)
    {
        $invoice = Invoice::with(['customer'])->findOrFail($id);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->invoiceService->sendEmail(
            $invoice,
            $data['email'],
            $data['message'] ?? null
        );

        return response()->json(['message' => 'Invoice sent successfully']);
    }
}


