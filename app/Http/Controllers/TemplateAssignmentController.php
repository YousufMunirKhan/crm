<?php

namespace App\Http\Controllers;

use App\Models\TemplateAssignment;
use Illuminate\Http\Request;

class TemplateAssignmentController extends Controller
{
    /**
     * Check if user is admin
     */
    private function checkAdmin()
    {
        $user = auth()->user();
        $role = $user->role->name ?? '';
        if (!in_array($role, ['Admin', 'System Admin'])) {
            abort(403, 'Only administrators can manage template assignments');
        }
    }

    public function index()
    {
        $this->checkAdmin();
        $assignments = TemplateAssignment::all()
            ->groupBy('function_type')
            ->map(function ($group) {
                return $group->map(function ($assignment) {
                    $template = null;
                    if ($assignment->template_type === 'email' && $assignment->template_id) {
                        $template = \App\Models\EmailTemplate::find($assignment->template_id);
                    } elseif ($assignment->template_type === 'sms' && $assignment->template_id) {
                        $template = \App\Models\MessageTemplate::find($assignment->template_id);
                    } elseif ($assignment->template_type === 'whatsapp' && $assignment->template_id) {
                        $template = \App\Models\WhatsAppTemplate::find($assignment->template_id);
                    }
                    return [
                        'id' => $assignment->id,
                        'function_type' => $assignment->function_type,
                        'template_type' => $assignment->template_type,
                        'template_id' => $assignment->template_id,
                        'is_active' => $assignment->is_active,
                        'template' => $template,
                    ];
                });
            });

        return response()->json($assignments);
    }

    public function update(Request $request)
    {
        $this->checkAdmin();
        $data = $request->validate([
            'function_type' => ['required', 'string'],
            'template_type' => ['required', 'string', 'in:email,sms,whatsapp'],
            'template_id' => ['nullable', 'integer'],
        ]);

        TemplateAssignment::updateOrCreate(
            [
                'function_type' => $data['function_type'],
                'template_type' => $data['template_type'],
            ],
            [
                'template_id' => $data['template_id'],
                'is_active' => $data['template_id'] ? true : false,
            ]
        );

        return response()->json(['message' => 'Template assignment updated successfully']);
    }

    public function getAssignment($functionType, $templateType)
    {
        $assignment = TemplateAssignment::where('function_type', $functionType)
            ->where('template_type', $templateType)
            ->where('is_active', true)
            ->first();

        return response()->json($assignment);
    }
}
