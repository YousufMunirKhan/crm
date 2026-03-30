<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\Communication;
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function __construct(
        private CommunicationService $communicationService
    ) {}

    public function index(Request $request)
    {
        $query = Communication::with(['customer', 'lead']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        $communications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($communications);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'channel' => ['required', 'in:whatsapp,email,sms'],
            'message' => ['required', 'string'],
            'to_number' => ['nullable', 'string'], // Custom WhatsApp/SMS number
            'to_email' => ['nullable', 'email'], // Custom email address
            'subject' => ['nullable', 'string'], // For email subject
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $lead = isset($data['lead_id']) ? Lead::findOrFail($data['lead_id']) : null;

        // Determine recipient based on channel and custom values
        $options = [];
        if ($data['channel'] === 'whatsapp' && !empty($data['to_number'])) {
            $options['to_number'] = $data['to_number'];
        } elseif ($data['channel'] === 'sms' && !empty($data['to_number'])) {
            $options['to_number'] = $data['to_number'];
        } elseif ($data['channel'] === 'email' && !empty($data['to_email'])) {
            $options['to_email'] = $data['to_email'];
        }
        if (!empty($data['subject'])) {
            $options['subject'] = $data['subject'];
        }

        $communication = $this->communicationService->send(
            $customer,
            $lead,
            $data['channel'],
            'outbound',
            $data['message'],
            $options
        );

        return response()->json($communication->load(['customer', 'lead']), 201);
    }

    public function show($id)
    {
        $communication = Communication::with(['customer', 'lead'])->findOrFail($id);
        return response()->json($communication);
    }
}


