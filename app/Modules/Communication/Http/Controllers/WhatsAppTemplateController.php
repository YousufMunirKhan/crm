<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Exceptions\WhatsAppGraphApiException;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\Communication\Models\WhatsAppTemplate;
use App\Modules\Communication\Services\WhatsAppServiceV2;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use App\Modules\Communication\Support\WhatsAppApiErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WhatsAppTemplateController extends Controller
{
    public function __construct()
    {
        $this->templateService = app(\App\Modules\Communication\Services\WhatsAppTemplateService::class);
    }

    private WhatsAppTemplateService $templateService;

    public function index(Request $request)
    {
        $query = WhatsAppTemplate::query();

        if ($request->filled('status')) {
            $statuses = array_filter(array_map(
                static fn ($s) => strtoupper(trim((string) $s)),
                explode(',', (string) $request->get('status'))
            ));
            if (!empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('category')) {
            $categories = array_filter(array_map(
                static fn ($c) => strtoupper(trim((string) $c)),
                explode(',', (string) $request->get('category'))
            ));
            if (!empty($categories)) {
                $query->whereIn('category', $categories);
            }
        }

        $templates = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($templates);
    }

    public function show($id)
    {
        $template = WhatsAppTemplate::findOrFail($id);
        return response()->json($template);
    }

    /**
     * Which named parameters this template expects + CRM-suggested values for a customer (optional).
     */
    public function parameterHints(Request $request, $id, WhatsAppServiceV2 $whatsAppServiceV2)
    {
        $template = WhatsAppTemplate::findOrFail($id);
        $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ]);

        $customer = $request->filled('customer_id') ? Customer::find($request->integer('customer_id')) : null;
        $lead = $request->filled('lead_id') ? Lead::find($request->integer('lead_id')) : null;

        return response()->json($whatsAppServiceV2->getParameterHintsForTemplate($template, $customer, $lead));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:512', 'unique:whatsapp_templates,name'],
            'category' => ['required', 'in:TRANSACTIONAL,MARKETING'],
            'language' => ['nullable', 'string'],
            'components' => ['required', 'array'],
            'components.*.type' => ['required', 'in:HEADER,BODY,FOOTER,BUTTONS'],
            'components.*.format' => ['required_if:components.*.type,HEADER', 'in:TEXT,IMAGE,VIDEO,DOCUMENT'],
            'components.*.text' => ['required_if:components.*.type,BODY', 'string'],
            'components.*.buttons' => ['required_if:components.*.type,BUTTONS', 'array'],
        ]);

        // Set default language if not provided
        $data['language'] = $data['language'] ?? 'en_US';

        try {
            $template = $this->templateService->createTemplate($data);

            return response()->json($template, 201);
        } catch (WhatsAppGraphApiException $e) {
            $body = WhatsAppApiErrorResponse::fromThrowable($e, 'Meta rejected template creation');

            return response()->json(array_merge($body, [
                'template' => WhatsAppTemplate::where('name', $data['name'])->first(),
            ]), 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'template' => WhatsAppTemplate::where('name', $data['name'])->first(),
            ], 422);
        }
    }

    /**
     * Preview the exact Graph API message payload and filled body/header the CRM would send.
     */
    public function preview(Request $request, WhatsAppServiceV2 $whatsAppServiceV2)
    {
        $data = $request->validate([
            'template_name' => ['required', 'string', 'max:512'],
            'template_params' => ['nullable', 'array'],
            'language' => ['nullable', 'string', 'max:32'],
            'sample_to' => ['nullable', 'string', 'max:32'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ]);

        try {
            $mergeCustomer = !empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
            $mergeLead = ($mergeCustomer && !empty($data['lead_id'])) ? Lead::find($data['lead_id']) : null;

            $preview = $whatsAppServiceV2->previewTemplatePayload(
                $data['template_name'],
                $data['template_params'] ?? [],
                $data['language'] ?? null,
                $data['sample_to'] ?? null,
                $mergeCustomer,
                $mergeLead
            );

            return response()->json($preview);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Template not found in CRM. Sync templates from Meta or create it here first.',
            ], 404);
        }
    }

    public function resubmit($id)
    {
        $template = WhatsAppTemplate::findOrFail($id);
        
        $template = $this->templateService->resubmitTemplate($template);

        return response()->json([
            'message' => 'Template resubmitted successfully',
            'template' => $template,
        ]);
    }

    public function sync()
    {
        $result = $this->templateService->syncTemplates();

        return response()->json([
            'message' => 'Templates synced',
            'synced' => $result['synced'],
            'errors' => $result['errors'],
        ]);
    }
}

