<?php

namespace App\Modules\ImportExport\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private $invoices) {}

    public function collection()
    {
        return $this->invoices;
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Customer Name',
            'Invoice Date',
            'Due Date',
            'Subtotal',
            'VAT',
            'Total',
            'Status',
            'Created At',
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->customer?->name,
            $invoice->invoice_date,
            $invoice->due_date,
            $invoice->subtotal,
            $invoice->vat_amount,
            $invoice->total,
            $invoice->status,
            $invoice->created_at,
        ];
    }
}

