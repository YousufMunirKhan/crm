<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ImportExport\Services\ImportExportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportController extends Controller
{
    public function __construct(
        private ImportExportService $importExportService
    ) {}

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        $file = $request->file('file');
        $type = $request->input('type', 'customers'); // customers, leads, invoices

        $preview = $this->importExportService->preview($file, $type);

        return response()->json($preview);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
            'type' => ['required', 'in:customers,leads,invoices'],
            'mapping' => ['required', 'array'],
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $mapping = $request->input('mapping');
        $skipDuplicates = $request->input('skip_duplicates', true);

        $result = $this->importExportService->import($file, $type, $mapping, $skipDuplicates);

        return response()->json($result);
    }

    public function export(Request $request)
    {
        $request->validate([
            'type' => ['required', 'in:customers,leads,invoices,tickets'],
            'format' => ['sometimes', 'in:csv,xlsx'],
        ]);

        $type = $request->input('type');
        $format = $request->input('format', 'xlsx');
        $filters = $request->except(['type', 'format']);

        return $this->importExportService->export($type, $format, $filters);
    }

    public function importLogs(Request $request)
    {
        $logs = \App\Modules\ImportExport\Models\ImportLog::orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }
}

