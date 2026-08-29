<?php

namespace App\Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoicePayment;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\CRM\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');

        $query = Invoice::with(['customer', 'items', 'creator', 'payments.receivedBy']);

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

        // Searched the customer's contact name only, so an invoice could not be
        // found by the company it was actually billed to.
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->search($search));
            });
        }

        $invoices = $query->orderBy('invoice_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($invoices);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['customer', 'items', 'creator', 'payments.receivedBy'])->findOrFail($id);
        return response()->json($invoice);
    }

    public function store(Request $request)
    {
        $customerId = $request->input('customer_id');
        $newCustomer = $request->input('customer');

        if ($newCustomer && is_array($newCustomer)) {
            $request->validate([
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
                'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
                'initial_payment' => ['nullable', 'array'],
                'initial_payment.payment_date' => ['required_with:initial_payment.amount', 'date'],
                'initial_payment.amount' => ['nullable', 'numeric', 'gt:0'],
                'initial_payment.method' => ['nullable', 'string', 'max:64'],
                'initial_payment.reference' => ['nullable', 'string', 'max:160'],
                'initial_payment.notes' => ['nullable', 'string', 'max:2000'],
            ]);
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
                'initial_payment' => ['nullable', 'array'],
                'initial_payment.payment_date' => ['required_with:initial_payment.amount', 'date'],
                'initial_payment.amount' => ['nullable', 'numeric', 'gt:0'],
                'initial_payment.method' => ['nullable', 'string', 'max:64'],
                'initial_payment.reference' => ['nullable', 'string', 'max:160'],
                'initial_payment.notes' => ['nullable', 'string', 'max:2000'],
            ]);
            $customerId = $request->input('customer_id');
        }

        $data = $request->only(['invoice_date', 'due_date', 'vat_rate', 'status', 'items']);
        $initialPayment = $this->initialPaymentData($request);

        $invoice = DB::transaction(function () use ($data, $customerId, $newCustomer, $initialPayment) {
            if ($newCustomer && is_array($newCustomer)) {
                $customer = Customer::create([
                    'type' => Customer::TYPE_CUSTOMER,
                    'name' => $newCustomer['name'],
                    'phone' => $newCustomer['phone'],
                    'email' => $newCustomer['email'] ?? null,
                    'address' => $newCustomer['address'] ?? null,
                    'vat_number' => $newCustomer['vat_number'] ?? null,
                    'created_by' => auth()->id(),
                ]);
                $data['customer_id'] = $customer->id;
            } else {
                $data['customer_id'] = $customerId;
            }

            $invoice = $this->invoiceService->create($data, auth()->id());

            if ($initialPayment) {
                if ((float) $initialPayment['amount'] > (float) $invoice->total + 0.01) {
                    throw ValidationException::withMessages([
                        'initial_payment.amount' => 'Payment amount cannot be greater than the invoice total.',
                    ]);
                }

                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'received_by_user_id' => auth()->id(),
                    'payment_date' => $initialPayment['payment_date'],
                    'amount' => $initialPayment['amount'],
                    'method' => $initialPayment['method'] ?? null,
                    'reference' => $initialPayment['reference'] ?? null,
                    'notes' => $initialPayment['notes'] ?? null,
                ]);

                $this->syncInvoicePaymentTotals($invoice);
            }

            return $invoice->fresh(['customer', 'items', 'payments.receivedBy']);
        });

        return response()->json($invoice, 201);
    }

    /**
     * Raises an invoice from a won lead.
     *
     * Previously a won deal had to be re-keyed into an invoice by hand, which
     * is where pricing errors and lost product attribution came from.
     */
    public function storeFromLead(Request $request, $leadId)
    {
        $lead = \App\Modules\CRM\Models\Lead::with(['items.product', 'customer'])->findOrFail($leadId);

        $data = $request->validate([
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:draft,sent'],
        ]);

        try {
            $invoice = $this->invoiceService->createFromLead($lead, $data, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(
            $invoice->fresh(['customer', 'items.product', 'lead']),
            201
        );
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
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        $invoice = $this->invoiceService->update($invoice, $data);

        // Always recompute from the ledger - including when there are no
        // payment rows, so a stale amount_paid cannot survive as "paid".
        $this->syncInvoicePaymentTotals($invoice);
        $invoice = $invoice->fresh(['customer', 'items', 'creator', 'payments.receivedBy']);

        return response()->json($invoice);
    }

    public function storePayment(Request $request, int $id)
    {
        $invoice = Invoice::with('payments')->findOrFail($id);

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $outstanding = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
        if ((float) $data['amount'] > $outstanding + 0.01) {
            return response()->json([
                'message' => 'Payment amount cannot be greater than the outstanding invoice balance.',
            ], 422);
        }

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'received_by_user_id' => auth()->id(),
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncInvoicePaymentTotals($invoice);

        return response()->json($invoice->fresh(['customer', 'items', 'creator', 'payments.receivedBy']));
    }

    public function destroyPayment(int $id, int $paymentId)
    {
        $invoice = Invoice::findOrFail($id);
        $payment = InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('id', $paymentId)
            ->firstOrFail();

        $payment->delete();
        $this->syncInvoicePaymentTotals($invoice);

        return response()->json($invoice->fresh(['customer', 'items', 'creator', 'payments.receivedBy']));
    }

    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return response()->noContent();
    }

    public function generatePDF($id)
    {
        $invoice = Invoice::with(['customer', 'items', 'payments.receivedBy'])->findOrFail($id);
        $pdf = $this->invoiceService->generatePDF($invoice);

        return $pdf->download($this->invoiceService->pdfFileName($invoice));
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

    /**
     * Delegates to the service so the payments ledger stays the only writer
     * of amount_paid.
     */
    private function syncInvoicePaymentTotals(Invoice $invoice): void
    {
        $this->invoiceService->syncPaymentTotals($invoice);
    }

    private function initialPaymentData(Request $request): ?array
    {
        $payment = $request->input('initial_payment');
        if (!is_array($payment) || (float) ($payment['amount'] ?? 0) <= 0) {
            return null;
        }

        return [
            'payment_date' => $payment['payment_date'],
            'amount' => $payment['amount'],
            'method' => $payment['method'] ?? null,
            'reference' => $payment['reference'] ?? null,
            'notes' => $payment['notes'] ?? null,
        ];
    }
}


