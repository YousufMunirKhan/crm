<?php

namespace App\Http\Controllers;

use App\Services\PosSupportIngestService;
use Illuminate\Http\Request;

/**
 * Public API for Desktop POS — authenticate with X-Api-Key (see config/pos-support.php).
 */
class PosSupportMessageController extends Controller
{
    public function store(Request $request, PosSupportIngestService $ingest)
    {
        $items = $request->all();
        if (!is_array($items) || (array_keys($items) !== range(0, count($items) - 1))) {
            return response()->json([
                'error' => 'Expected a JSON array of support message objects at the root.',
            ], 422);
        }

        $validated = validator($items, [
            '*.id' => ['required'],
            '*.shopName' => ['nullable', 'string', 'max:255'],
            '*.telephone' => ['nullable', 'string', 'max:64'],
            '*.address' => ['nullable', 'string', 'max:512'],
            '*.computerName' => ['nullable', 'string', 'max:128'],
            '*.message' => ['nullable', 'string'],
            '*.status' => ['nullable', 'string', 'max:64'],
            '*.createdAt' => ['nullable', 'string'],
            '*.sentAt' => ['nullable', 'string'],
        ])->validate();

        $result = $ingest->syncFromPos($validated);

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'items' => $result['items'],
        ], 200);
    }

    public function status(Request $request, PosSupportIngestService $ingest)
    {
        $request->validate([
            'ids' => ['required', 'string', 'max:2000'],
        ]);

        $data = $ingest->statusForIds($request->query('ids'));

        return response()->json($data);
    }
}
