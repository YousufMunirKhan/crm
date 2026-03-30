<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    /**
     * Check if user is admin
     */
    private function checkAdmin()
    {
        $user = auth()->user();
        $role = $user->role->name ?? '';
        if (!in_array($role, ['Admin', 'System Admin'])) {
            abort(403, 'Only administrators can access message templates');
        }
    }

    /**
     * List active message templates for sending (e.g. from customer page). Any authenticated user.
     */
    public function listForSending(Request $request)
    {
        $templates = MessageTemplate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'message', 'category']);
        return response()->json($templates);
    }

    public function index(Request $request)
    {
        $this->checkAdmin();
        $query = MessageTemplate::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }

        $templates = $query->with('creator')->orderBy('created_at', 'desc')->get();

        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $this->checkAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'message' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $data['created_by'] = auth()->id();
        $data['is_active'] = $data['is_active'] ?? true;

        $template = MessageTemplate::create($data);

        return response()->json($template->load('creator'), 201);
    }

    public function show($id)
    {
        $this->checkAdmin();
        $template = MessageTemplate::findOrFail($id);
        return response()->json($template->load('creator'));
    }

    public function update(Request $request, $id)
    {
        $this->checkAdmin();
        $template = MessageTemplate::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string'],
            'message' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $template->update($data);

        return response()->json($template->load('creator'));
    }

    public function destroy($id)
    {
        $this->checkAdmin();
        $template = MessageTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function duplicate($id)
    {
        $this->checkAdmin();
        $template = MessageTemplate::findOrFail($id);
        
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->created_by = auth()->id();
        $newTemplate->save();

        return response()->json($newTemplate->load('creator'), 201);
    }
}
