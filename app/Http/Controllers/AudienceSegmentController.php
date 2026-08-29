<?php

namespace App\Http\Controllers;

use App\Models\AudienceSegment;
use Illuminate\Http\Request;

/**
 * Saved audiences for campaign sends.
 */
class AudienceSegmentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            AudienceSegment::visibleTo($request->user()->id)
                ->with('creator:id,name')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        return response()->json(AudienceSegment::create($data), 201);
    }

    public function show(Request $request, int $id)
    {
        return response()->json(
            AudienceSegment::visibleTo($request->user()->id)->findOrFail($id)
        );
    }

    public function update(Request $request, int $id)
    {
        $segment = AudienceSegment::findOrFail($id);

        if (! $this->canEdit($request, $segment)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $segment->update($this->validated($request));

        return response()->json($segment->fresh());
    }

    public function destroy(Request $request, int $id)
    {
        $segment = AudienceSegment::findOrFail($id);

        if (! $this->canEdit($request, $segment)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $segment->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'filters' => ['required', 'array'],
            'is_shared' => ['nullable', 'boolean'],
        ]);
    }

    /** The owner, or an admin, may change a segment. */
    private function canEdit(Request $request, AudienceSegment $segment): bool
    {
        $user = $request->user();

        return $segment->created_by === $user->id
            || $user->isRole('Admin')
            || $user->isRole('System Admin');
    }
}
