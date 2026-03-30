<?php

namespace App\Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Customer;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Services\TicketService;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\POS\Models\PosEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $customer = Customer::updateOrCreate(
            ['phone' => $data['phone']],
            $data
        );

        PosEvent::create([
            'event_type' => 'customer',
            'payload' => $data,
            'external_id' => $data['external_id'],
        ]);

        return response()->json($customer, 201);
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

        $ticket = $this->ticketService->create($data);

        PosEvent::create([
            'event_type' => 'ticket',
            'payload' => $data,
            'external_id' => $data['external_id'],
        ]);

        // Broadcast notification
        // event(new \App\Events\NewTicketCreated($ticket));

        return response()->json($ticket->load('customer'), 201);
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

        $customer = Customer::firstOrCreate(
            ['phone' => $data['customer_phone']],
            ['name' => $data['customer_phone']]
        );

        $invoice = $this->invoiceService->create([
            'customer_id' => $customer->id,
            'items' => $data['items'],
            'status' => 'paid',
        ]);

        // Mark as paid
        $invoice->update([
            'amount_paid' => $invoice->total,
        ]);

        PosEvent::create([
            'event_type' => 'sale',
            'payload' => $data,
            'external_id' => $data['external_id'],
        ]);

        return response()->json($invoice->load(['customer', 'items']), 201);
    }
}


