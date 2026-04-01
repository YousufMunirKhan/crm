<?php

namespace App\Modules\ImportExport\Services;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\ImportExport\Models\ImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportExportService
{
    public function preview($file, string $type): array
    {
        $data = Excel::toArray(new class implements ToArray, WithHeadingRow {
            public function array(array $array)
            {
                return $array;
            }
        }, $file);

        $rows = $data[0] ?? [];
        $headers = array_keys($rows[0] ?? []);

        return [
            'headers' => $headers,
            'preview' => array_slice($rows, 0, 5),
            'total_rows' => count($rows),
        ];
    }

    public function import($file, string $type, array $mapping, bool $skipDuplicates): array
    {
        $data = Excel::toArray(new class implements ToArray, WithHeadingRow {
            public function array(array $array)
            {
                return $array;
            }
        }, $file);

        $rows = $data[0] ?? [];
        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            $log = ImportLog::create([
                'type' => $type,
                'total_rows' => count($rows),
                'status' => 'processing',
            ]);

            foreach ($rows as $index => $row) {
                try {
                    $mappedData = $this->mapRow($row, $mapping);

                    if ($type === 'customers') {
                        if ($skipDuplicates && Customer::where('phone', $mappedData['phone'] ?? '')->exists()) {
                            $skipped++;
                            continue;
                        }
                        // Customers imported here default to type 'prospect' unless mapping includes type.
                        Customer::create($mappedData);
                    } elseif ($type === 'leads') {
                        if (!isset($mappedData['customer_id'])) {
                            throw new \Exception('Customer ID is required for leads');
                        }
                        $lead = Lead::create($mappedData);
                        // Keep customer type in sync when importing leads that might already be won
                        $lead->customer?->syncTypeFromLeads();
                    } elseif ($type === 'invoices') {
                        if (!isset($mappedData['customer_id'])) {
                            throw new \Exception('Customer ID is required for invoices');
                        }
                        // Invoice creation logic here
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 2, // +2 for header and 1-based index
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $log->update([
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'status' => 'completed',
            ]);

            DB::commit();

            return [
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function export(string $type, string $format, array $filters)
    {
        $query = match ($type) {
            'customers' => Customer::query(),
            'leads' => Lead::with(['customer', 'assignee']),
            'invoices' => Invoice::with(['customer', 'items']),
            'tickets' => Ticket::with(['customer', 'assignee', 'assignees']),
            default => throw new \InvalidArgumentException("Unknown export type: {$type}"),
        };

        // Apply filters
        foreach ($filters as $key => $value) {
            if ($value) {
                $query->where($key, $value);
            }
        }

        $data = $query->get();

        $exportClass = match ($type) {
            'customers' => \App\Modules\ImportExport\Exports\CustomersExport::class,
            'leads' => \App\Modules\ImportExport\Exports\LeadsExport::class,
            'invoices' => \App\Modules\ImportExport\Exports\InvoicesExport::class,
            'tickets' => \App\Modules\ImportExport\Exports\TicketsExport::class,
        };

        $extension = $format === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(new $exportClass($data), "{$type}_export.{$extension}");
    }

    private function mapRow(array $row, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $dbField => $fileColumn) {
            if (isset($row[$fileColumn])) {
                $mapped[$dbField] = $row[$fileColumn];
            }
        }

        return $mapped;
    }
}

