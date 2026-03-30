<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\WhatsAppTemplate;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use Illuminate\Http\Request;

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

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
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

        $template = $this->templateService->createTemplate($data);

        return response()->json($template, 201);
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

