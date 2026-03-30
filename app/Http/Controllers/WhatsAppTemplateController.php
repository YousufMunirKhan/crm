<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WhatsAppTemplateController extends Controller
{
    /**
     * Check if user is admin
     */
    private function checkAdmin()
    {
        $user = auth()->user();
        $role = $user->role->name ?? '';
        if (!in_array($role, ['Admin', 'System Admin'])) {
            abort(403, 'Only administrators can access WhatsApp templates');
        }
    }

    public function index(Request $request)
    {
        $this->checkAdmin();
        $query = WhatsAppTemplate::query();

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
            'media_url' => ['nullable', 'string'],
            'media_type' => ['nullable', 'string', 'in:image,video,document'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $data['created_by'] = auth()->id();
        $data['is_active'] = $data['is_active'] ?? true;

        $template = WhatsAppTemplate::create($data);

        return response()->json($template->load('creator'), 201);
    }

    public function show($id)
    {
        $this->checkAdmin();
        $template = WhatsAppTemplate::findOrFail($id);
        return response()->json($template->load('creator'));
    }

    public function update(Request $request, $id)
    {
        $this->checkAdmin();
        $template = WhatsAppTemplate::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string'],
            'message' => ['sometimes', 'string'],
            'media_url' => ['nullable', 'string'],
            'media_type' => ['nullable', 'string', 'in:image,video,document'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $template->update($data);

        return response()->json($template->load('creator'));
    }

    public function destroy($id)
    {
        $this->checkAdmin();
        $template = WhatsAppTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function duplicate($id)
    {
        $this->checkAdmin();
        $template = WhatsAppTemplate::findOrFail($id);
        
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->created_by = auth()->id();
        $newTemplate->save();

        return response()->json($newTemplate->load('creator'), 201);
    }

    public function uploadMedia(Request $request)
    {
        $this->checkAdmin();
        $request->validate([
            'media' => ['required', 'file', 'mimes:jpeg,png,gif,mp4,pdf,doc,docx', 'max:10240'],
        ]);

        $file = $request->file('media');
        $mediaType = 'image';
        
        if (str_starts_with($file->getMimeType(), 'video/')) {
            $mediaType = 'video';
        } elseif (in_array($file->getMimeType(), ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
            $mediaType = 'document';
        }

        $path = $file->store('whatsapp-templates', 'public');
        $url = '/storage/' . $path;

        return response()->json([
            'url' => $url,
            'type' => $mediaType,
        ]);
    }
}
