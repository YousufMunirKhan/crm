<?php

namespace App\Http\Controllers;

use App\Modules\CRM\Models\Customer;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerPortalController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::where('phone', $request->phone)->first();

        if (!$customer || !Hash::check($request->password, $customer->portal_password ?? '')) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $customer->createToken('portal-token')->plainTextToken;

        return response()->json([
            'customer' => $customer,
            'token' => $token,
        ]);
    }

    public function dashboard(Request $request)
    {
        $customer = $request->user();

        $tickets = Ticket::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $invoices = Invoice::where('customer_id', $customer->id)
            ->orderBy('invoice_date', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'customer' => $customer,
            'tickets' => $tickets,
            'invoices' => $invoices,
        ]);
    }

    public function tickets(Request $request)
    {
        $customer = $request->user();

        $tickets = Ticket::where('customer_id', $customer->id)
            ->with(['assignee', 'messages'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($tickets);
    }

    public function invoices(Request $request)
    {
        $customer = $request->user();

        $invoices = Invoice::where('customer_id', $customer->id)
            ->with('items')
            ->orderBy('invoice_date', 'desc')
            ->paginate(15);

        return response()->json($invoices);
    }

    public function createTicket(Request $request)
    {
        $customer = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string'],
            'description' => ['required', 'string'],
        ]);

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => 'medium',
            'status' => 'open',
        ]);

        return response()->json($ticket, 201);
    }
}

