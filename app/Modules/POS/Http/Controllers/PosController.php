<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoicePayment;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\POS\Models\PosEvent;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POS integration endpoints. Guarded by the `pos.key` middleware.
 *
 * Every endpoint is idempotent on (event_type, external_id): the POS may retry
 * a request after a timeout without duplicating customers, tickets or invoices.
 */
class PosController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private InvoiceService $invoiceService
    ) {}

    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'external_id' => ['required', 'string'],
            'name' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'postcode' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
        ]);

        if ($replay = $this->replayOf('customer', $data['external_id'])) {
            $customer = Customer::find($replay->payload['_result_id'] ?? null);
            if ($customer) {
                return response()->json($customer, 200);
            }
        }

        return DB::transaction(function () use ($data) {
            $customer = Customer::updateOrCreate(
                ['phone' => $data['phone']],
                $data
            );

            $this->recordEvent('customer', $data, $customer->id);

            return response()->json($customer, 201);
        });
    }

    public function storeTicket(Request $request)
    {
        $data = $request->validate([
            'external_id' => ['required', 'string'],
            'customer_phone' => ['required', 'string'],
            'subject' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
        ]);

        if ($replay = $this->replayOf('ticket', $data['external_id'])) {
            $ticket = Ticket::with('customer')->find($replay->payload['_result_id'] ?? null);
            if ($ticket) {
                return response()->json($ticket, 200);
            }
        }

        return DB::transaction(function () use ($data) {
            $ticket = $this->ticketService->create($data);

            $this->recordEvent('ticket', $data, $ticket->id);

            return response()->json($ticket->load('customer'), 201);
        });
    }

    public function storeSale(Request $request)
    {
        $data = $request->validate([
            'external_id' => ['required', 'string'],
            'customer_phone' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        if ($replay = $this->replayOf('sale', $data['external_id'])) {
            $invoice = Invoice::with(['customer', 'items'])->find($replay->payload['_result_id'] ?? null);
            if ($invoice) {
                return response()->json($invoice, 200);
            }
        }

        return DB::transaction(function () use ($data) {
            $customer = Customer::firstOrCreate(
                ['phone' => $data['customer_phone']],
                ['name' => $data['customer_phone']]
            );

            $invoice = $this->invoiceService->create([
                'customer_id' => $customer->id,
                'items' => $data['items'],
                'status' => 'sent',
            ]);

            // A POS sale is paid in full at the till. Record it in the payments
            // ledger - writing amount_paid directly would be undone the next
            // time the invoice totals are recomputed from that ledger.
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'received_by_user_id' => null,
                'payment_date' => now()->toDateString(),
                'amount' => $invoice->total,
                'method' => 'pos',
                'reference' => 'POS sale '.$data['external_id'],
            ]);

            $this->invoiceService->syncPaymentTotals($invoice);

            $this->recordEvent('sale', $data, $invoice->id);

            return response()->json($invoice->fresh(['customer', 'items']), 201);
        });
    }

    /**
     * A prior successfully-processed event with this external id, if any.
     */
    private function replayOf(string $type, string $externalId): ?PosEvent
    {
        return PosEvent::query()
            ->where('event_type', $type)
            ->where('external_id', $externalId)
            ->latest('id')
            ->first();
    }

    /**
     * Stores the event and the id of what it produced, so a retry can return
     * the original resource instead of creating a second one.
     */
    private function recordEvent(string $type, array $payload, int $resultId): void
    {
        PosEvent::create([
            'event_type' => $type,
            'payload' => $payload + ['_result_id' => $resultId],
            'external_id' => $payload['external_id'],
        ]);
    }
}
